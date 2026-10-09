<?php

declare(strict_types=1);

namespace LeanCaptain\LaraEmail\Rules;

use Closure;
use Egulias\EmailValidator\Validation\DNSGetRecordWrapper;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Validation\ValidationRule;
use RuntimeException;

class UsableEmailDomain implements ValidationRule
{
    /** @var array<string, int>|null */
    private ?array $blockedDomains = null;

    public function __construct(
        private readonly DNSGetRecordWrapper $dnsRecords = new DNSGetRecordWrapper,
        private readonly ?Repository $cache = null,
    ) {}

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

        $cacheKey = 'lara-email:mx:v1:'.hash('sha256', $domain);

        if ($this->hasCachedMailRecord($cacheKey)) {
            return;
        }

        $records = $this->dnsRecords->getRecords($domain, DNS_MX);

        if ($records->withError()) {
            $fail('We could not verify the email domain right now. Please try again.');

            return;
        }

        if (! $this->hasUsableMailRecord($records->getRecords())) {
            $fail('Please use an email address with a domain that can receive email.');

            return;
        }

        $this->cacheMailRecord($cacheKey, $records->getRecords());
    }

    private function hasCachedMailRecord(string $key): bool
    {
        try {
            return $this->cache?->get($key) === true;
        } catch (Exception) {
            // A cache outage must not prevent a live DNS check.
            return false;
        }
    }

    /** @param list<array<array-key, mixed>> $records */
    private function cacheMailRecord(string $key, array $records): void
    {
        if ($this->cache === null) {
            return;
        }

        $ttl = 300;

        foreach ($records as $record) {
            if (($record['type'] ?? null) !== 'MX') {
                continue;
            }

            $recordTtl = $record['ttl'] ?? null;

            if (! is_int($recordTtl) || $recordTtl <= 0) {
                return;
            }

            $ttl = min($ttl, $recordTtl);
        }

        try {
            $this->cache->put($key, true, $ttl);
        } catch (Exception) {
            // Validation already succeeded; caching is optional.
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
