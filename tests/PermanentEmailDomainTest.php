<?php

use Egulias\EmailValidator\Validation\DNSGetRecordWrapper;
use Egulias\EmailValidator\Validation\DNSRecords;
use LeanCaptain\LaraEmail\Rules\PermanentEmailDomain;

beforeEach(function (): void {
    $this->dnsRecords = Mockery::mock(DNSGetRecordWrapper::class);
    $this->rule = new PermanentEmailDomain($this->dnsRecords);
});

afterEach(function (): void {
    Mockery::close();
});

it('rejects example and disposable domains without a DNS lookup', function (string $email): void {
    $this->dnsRecords->shouldNotReceive('getRecords');

    expect(emailDomainErrors($this->rule, $email))->toHaveCount(1);
})->with([
    'example.com' => 'john@example.com',
    'example.net' => 'john@example.net',
    'example.org' => 'john@example.org',
    'reserved subdomain' => 'john@mail.example.com',
    'Yopmail' => 'john@yopmail.com',
    'mixed case' => 'John@YOPMAIL.COM',
    'trailing dot' => 'john@yopmail.com.',
    'disposable subdomain' => 'john@mail.yopmail.com',
    'Mailinator' => 'john@mailinator.com',
]);

it('normalizes permanent domains before resolving their MX records', function (string $email, string $domain): void {
    $this->dnsRecords->shouldReceive('getRecords')->once()->with($domain, DNS_MX)
        ->andReturn(new DNSRecords([
            ['type' => 'A', 'ip' => '192.0.2.1'],
            ['type' => 'MX', 'target' => 'mail.'.$domain, 'pri' => 10],
        ]));

    expect(emailDomainErrors($this->rule, $email))->toBeEmpty();
})->with([
    'Gmail' => ['john@gmail.com', 'gmail.com'],
    'custom domain' => ['john@company.com', 'company.com'],
    'mixed case' => ['john@COMPANY.COM', 'company.com'],
    'subdomain' => ['john@mail.company.com', 'mail.company.com'],
    'lookalike suffix' => ['john@notyopmail.com', 'notyopmail.com'],
    'lookalike prefix' => ['john@yopmail.com.company.com', 'yopmail.com.company.com'],
    'internationalized domain' => ['john@bücher.de', 'xn--bcher-kva.de'],
]);

it('rejects missing or unusable mail records', function (DNSRecords $records): void {
    $this->dnsRecords->shouldReceive('getRecords')->once()->with('company.com', DNS_MX)->andReturn($records);

    expect(emailDomainErrors($this->rule, 'john@company.com'))->toHaveCount(1);
})->with([
    'no records' => new DNSRecords([]),
    'DNS failure' => new DNSRecords([], true),
    'null MX' => new DNSRecords([['type' => 'MX', 'target' => '.', 'pri' => 0]]),
    'empty target' => new DNSRecords([['type' => 'MX', 'target' => '']]),
    'missing target' => new DNSRecords([['type' => 'MX']]),
    'missing record type' => new DNSRecords([['target' => 'mail.company.com']]),
    'A record only' => new DNSRecords([['type' => 'A', 'ip' => '192.0.2.1']]),
]);

it('rejects malformed input without a DNS lookup', function (mixed $email): void {
    $this->dnsRecords->shouldNotReceive('getRecords');

    expect(emailDomainErrors($this->rule, $email))->toHaveCount(1);
})->with([
    'no separator' => 'not-an-email',
    'empty domain' => 'john@',
    'invalid IDN' => 'john@bad..com',
    'array' => [['john@company.com']],
    'integer' => 123,
    'null' => [null],
]);

it('can be constructed without a Laravel service provider', function (): void {
    expect(new PermanentEmailDomain)->toBeInstanceOf(PermanentEmailDomain::class);
});

it('integrates with Laravel validation and returns an email error', function (): void {
    $this->dnsRecords->shouldNotReceive('getRecords');

    $validator = emailValidator()->make(
        ['email' => 'john@yopmail.com'],
        ['email' => ['bail', 'required', 'email', $this->rule]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
});

it('allows permanent domains through Laravel validation', function (): void {
    $this->dnsRecords->shouldReceive('getRecords')->once()->with('company.com', DNS_MX)
        ->andReturn(new DNSRecords([['type' => 'MX', 'target' => 'mail.company.com']]));

    $validator = emailValidator()->make(
        ['email' => 'john@company.com'],
        ['email' => ['bail', 'required', 'email', $this->rule]],
    );

    expect($validator->passes())->toBeTrue();
});

it('lets Laravel bail on syntax errors before querying DNS', function (): void {
    $this->dnsRecords->shouldNotReceive('getRecords');

    $validator = emailValidator()->make(
        ['email' => 'not-an-email'],
        ['email' => ['bail', 'required', 'email', $this->rule]],
    );

    expect($validator->fails())->toBeTrue();
});

it('ships a normalized blocklist and matching source metadata', function (): void {
    $root = dirname(__DIR__);
    $list = file_get_contents($root.'/resources/disposable-email-domains.txt');
    $domains = explode("\n", trim($list));
    $metadata = json_decode(file_get_contents($root.'/resources/disposable-email-domains.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(count($domains))->toBeGreaterThan(1000)
        ->and($domains)->toContain('yopmail.com', 'mailinator.com')
        ->not->toContain('gmail.com', 'outlook.com', 'yahoo.com')
        ->and(count(array_unique($domains)))->toBe(count($domains))
        ->and($metadata['domains'])->toBe(count($domains))
        ->and($metadata['sha256'])->toBe(hash('sha256', $list))
        ->and($metadata['commit'])->toMatch('/^[a-f0-9]{40}$/');
});
