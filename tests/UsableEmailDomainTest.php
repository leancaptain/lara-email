<?php

use Egulias\EmailValidator\Validation\DNSGetRecordWrapper;
use Egulias\EmailValidator\Validation\DNSRecords;
use Illuminate\Contracts\Cache\Repository;
use LeanCaptain\LaraEmail\Rules\UsableEmailDomain;

beforeEach(function (): void {
    $this->dnsRecords = Mockery::mock(DNSGetRecordWrapper::class);
    $this->rule = new UsableEmailDomain($this->dnsRecords);
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
    expect(new UsableEmailDomain)->toBeInstanceOf(UsableEmailDomain::class);
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

it('caches successful lookups using the shortest MX TTL capped at five minutes', function (array $ttls, int $expectedTtl): void {
    $cache = Mockery::mock(Repository::class);
    $key = 'lara-email:mx:v1:'.hash('sha256', 'company.com');
    $cache->shouldReceive('get')->once()->with($key)->andReturn(null);
    $cache->shouldReceive('put')->once()->with($key, true, $expectedTtl)->andReturn(true);
    $this->dnsRecords->shouldReceive('getRecords')->once()->with('company.com', DNS_MX)
        ->andReturn(new DNSRecords(array_map(
            fn (int $ttl): array => ['type' => 'MX', 'target' => 'mail.company.com', 'ttl' => $ttl],
            $ttls,
        )));

    $rule = new UsableEmailDomain($this->dnsRecords, $cache);

    expect(emailDomainErrors($rule, 'john@company.com'))->toBeEmpty();
})->with([
    'shortest TTL' => [[120, 60], 60],
    'five minute cap' => [[3600], 300],
]);

it('uses cached success across normalized addresses without querying DNS', function (): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->twice()
        ->with('lara-email:mx:v1:'.hash('sha256', 'company.com'))->andReturn(true);
    $cache->shouldNotReceive('put');
    $this->dnsRecords->shouldNotReceive('getRecords');

    expect(emailDomainErrors(new UsableEmailDomain($this->dnsRecords, $cache), 'john@COMPANY.COM'))->toBeEmpty()
        ->and(emailDomainErrors(new UsableEmailDomain($this->dnsRecords, $cache), 'jane@company.com.'))->toBeEmpty();
});

it('does not cache successful lookups without a positive integer TTL', function (mixed $ttl): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->andReturn(null);
    $cache->shouldNotReceive('put');
    $this->dnsRecords->shouldReceive('getRecords')->once()
        ->andReturn(new DNSRecords([['type' => 'MX', 'target' => 'mail.company.com', 'ttl' => $ttl]]));

    expect(emailDomainErrors(new UsableEmailDomain($this->dnsRecords, $cache), 'john@company.com'))->toBeEmpty();
})->with([null, 0, -1, '120']);

it('does not cache failed checks and distinguishes DNS errors from missing MX records', function (DNSRecords $records, string $message): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->twice()->andReturn(null);
    $cache->shouldNotReceive('put');
    $this->dnsRecords->shouldReceive('getRecords')->twice()->andReturn($records);
    $rule = new UsableEmailDomain($this->dnsRecords, $cache);

    expect(emailDomainErrors($rule, 'john@company.com'))->toBe([$message])
        ->and(emailDomainErrors($rule, 'john@company.com'))->toBe([$message]);
})->with([
    'lookup error' => [new DNSRecords([], true), 'We could not verify the email domain right now. Please try again.'],
    'missing MX' => [new DNSRecords([]), 'Please use an email address with a domain that can receive email.'],
    'null MX' => [new DNSRecords([['type' => 'MX', 'target' => '.', 'ttl' => 60]]), 'Please use an email address with a domain that can receive email.'],
]);

it('checks the blocklist before consulting cached DNS results', function (): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldNotReceive('get');
    $cache->shouldNotReceive('put');
    $this->dnsRecords->shouldNotReceive('getRecords');

    expect(emailDomainErrors(new UsableEmailDomain($this->dnsRecords, $cache), 'john@yopmail.com'))->toHaveCount(1);
});

it('falls back to DNS when cache reads fail and accepts success when cache writes fail', function (): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->andThrow(new RuntimeException('Cache unavailable'));
    $cache->shouldReceive('put')->once()->andThrow(new RuntimeException('Cache unavailable'));
    $this->dnsRecords->shouldReceive('getRecords')->once()
        ->andReturn(new DNSRecords([['type' => 'MX', 'target' => 'mail.company.com', 'ttl' => 60]]));

    expect(emailDomainErrors(new UsableEmailDomain($this->dnsRecords, $cache), 'john@company.com'))->toBeEmpty();
});

it('rechecks DNS after a cached success expires', function (): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->twice()->andReturn(true, null);
    $cache->shouldNotReceive('put');
    $this->dnsRecords->shouldReceive('getRecords')->once()->andReturn(new DNSRecords([]));
    $rule = new UsableEmailDomain($this->dnsRecords, $cache);

    expect(emailDomainErrors($rule, 'john@company.com'))->toBeEmpty()
        ->and(emailDomainErrors($rule, 'john@company.com'))->toBe(['Please use an email address with a domain that can receive email.']);
});
