<?php

declare(strict_types=1);

namespace LeanCaptain\LaraEmail\Rules;

use Closure;
use Egulias\EmailValidator\Validation\DNSGetRecordWrapper;
use Illuminate\Contracts\Validation\ValidationRule;
use RuntimeException;

class PermanentEmailDomain implements ValidationRule
{
    /** @var array<string, int>|null */
    private ?array $blockedDomains = null;

    public function __construct(private readonly DNSGetRecordWrapper $dnsRecords = new DNSGetRecordWrapper) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($separator = strrpos($value, '@')) === false) {
            $fail('Please enter a valid email address.');

            return;
        }

        $domain = rtrim(substr($value, $separator + 1), '.');

        if ($domain === '') {
            $fail('Please enter a valid email address.');

            return;
        }

        $domain = idn_to_ascii(
            $domain,
            IDNA_DEFAULT,
            INTL_IDNA_VARIANT_UTS46,
        );

        if ($domain === false || $domain === '' || $this->isBlocked($domain)) {
            $fail('Please use a permanent email address. Example and disposable email addresses are not allowed.');

            return;
        }

        $records = $this->dnsRecords->getRecords($domain, DNS_MX);

        if ($records->withError() || ! $this->hasUsableMailRecord($records->getRecords())) {
            $fail('Please use an email address with a domain that can receive email.');
        }
    }

    /** @param list<array<array-key, mixed>> $records */
    private function hasUsableMailRecord(array $records): bool
    {
        return array_any($records, fn (array $record): bool => ($record['type'] ?? null) === 'MX'
            && ! empty($record['target'])
            && $record['target'] !== '.');
    }

    private function isBlocked(string $domain): bool
    {
        if ($this->blockedDomains === null) {
            $list = file_get_contents(dirname(__DIR__, 2).'/resources/disposable-email-domains.txt');

            if ($list === false) {
                throw new RuntimeException('The bundled disposable email domain list could not be read.');
            }

            $this->blockedDomains = array_flip([
                'example.com',
                'example.net',
                'example.org',
                ...explode("\n", trim($list)),
            ]);
        }

        while (str_contains($domain, '.')) {
            if (isset($this->blockedDomains[$domain])) {
                return true;
            }

            $domain = substr($domain, strpos($domain, '.') + 1);
        }

        return false;
    }
}
