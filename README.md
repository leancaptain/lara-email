# Lara Email

[![Tests](https://github.com/leancaptain/lara-email/actions/workflows/tests.yml/badge.svg)](https://github.com/leancaptain/lara-email/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A small Laravel validation rule that rejects example and disposable email domains
and requires a usable MX record. No service provider, configuration publishing,
database, or external API account is needed.

## Requirements

- PHP 8.4 or later within PHP 8.x.
- Laravel 13, or Illuminate Validation 13 for standalone use.
- A runtime that supports `dns_get_record()` and can perform DNS lookups.

Internationalized domains are supported through PHP's `intl` extension or the
included Symfony IDN polyfill. Egulias supplies DNS error handling; Laravel's
validation component already requires it. The rule itself depends only on
Illuminate contracts, rather than the entire Laravel framework.

## Installation

Install the package with Composer:

```sh
composer require leancaptain/lara-email
```

## Usage

Add the rule to a Form Request or a call to Laravel's validator:

```php
use LeanCaptain\LaraEmail\Rules\UsableEmailDomain;

public function rules(): array
{
    return [
        'email' => ['bail', 'required', 'string', 'email', new UsableEmailDomain],
    ];
}
```

Laravel's `email` rule validates syntax. `bail` stops validation before the DNS
lookup if an earlier rule fails. Always pair `UsableEmailDomain` with `email`;
the domain rule does not validate the complete email address.

The rule:

- Rejects `example.com`, `example.net`, `example.org`, and their subdomains.
- Rejects bundled disposable domains and their subdomains.
- Normalizes domain case and internationalized names to ASCII.
- Requires an MX record with a nonempty target other than `.` for the exact email domain.
- Rejects missing MX records and null MX records. DNS lookup errors fail validation
  with a separate message asking the user to retry.

For example, `john@yopmail.com` fails without a DNS lookup. A permanent domain
passes only when its MX lookup returns a usable record. A subdomain such as
`john@mail.company.com` is checked directly; its parent's MX records do not satisfy
the rule.

Do not add `email:rfc,dns` alongside this rule unless you intentionally want an
additional DNS check. Egulias's DNS validation can use A/AAAA records as fallback;
this package specifically requires MX records.

### Apply only in selected environments

The rule runs whenever it is applied. Your application can choose when to require it:

```php
use Illuminate\Validation\Rule;
use LeanCaptain\LaraEmail\Rules\UsableEmailDomain;

'email' => [
    'bail', 'required', 'string', 'email',
    Rule::when(app()->isProduction(), [new UsableEmailDomain]),
],
```

### Optional DNS caching

Pass your application's cache store to avoid repeated lookups for the same domain:

```php
use Illuminate\Support\Facades\Cache;
use LeanCaptain\LaraEmail\Rules\UsableEmailDomain;

'email' => [
    'bail', 'required', 'string', 'email',
    new UsableEmailDomain(cache: Cache::store()),
],
```

Caching is disabled by default. Successful MX checks are cached for the shortest
returned MX TTL, capped at five minutes. Responses with missing, zero, or invalid
TTLs are not cached. Failed checks and DNS errors are never cached. The blocklist
is checked on every validation, including cache hits. Cache failures fall back to
live DNS checks; a failed cache write does not invalidate a successful check.

### Validation messages

| Failure                                           | Message                                                                                       |
|---------------------------------------------------|-----------------------------------------------------------------------------------------------|
| Non-string input, missing `@`, or empty domain    | Please enter a valid email address.                                                           |
| Invalid IDN, example domain, or disposable domain | Please use a permanent email address. Example and disposable email addresses are not allowed. |
| No usable MX record                               | Please use an email address with a domain that can receive email.                             |
| DNS lookup error                                  | We could not verify the email domain right now. Please try again.                             |

### Limitations

Uncached DNS lookups are synchronous. Resolver latency can slow validation; the
native DNS function does not offer a per-call timeout. Optional caching reduces
repeat lookups, but cached results may lag DNS changes until they expire.
Temporary DNS errors still fail validation and ask the user to retry; the rule
does not automatically retry. Apply it where a domain check is appropriate, such
as registration.

An MX record does not prove that a mailbox exists or belongs to the user, or that
its mail server is reachable. Require email verification in the application.
Domains that accept mail through A/AAAA fallback alone will fail this rule.

The disposable list is a bundled snapshot: newly created providers may not be
listed yet, and legitimate domains may occasionally be listed upstream. Existing
accounts are not changed. Report list corrections to the
[upstream project](https://github.com/disposable-email-domains/disposable-email-domains).

## Updates

The disposable-domain list is bundled with the package. Applications receive
reviewed list changes through package releases. Update within the installed
version constraint with:

```sh
composer update leancaptain/lara-email
```

Review the [release notes](CHANGELOG.md) and test validation in your application
before deploying an update.

## Contributing and support

Report package bugs through [GitHub issues](https://github.com/leancaptain/lara-email/issues).
See [CONTRIBUTING.md](https://github.com/leancaptain/lara-email/blob/main/CONTRIBUTING.md)
for development setup, checks, list updates, and the maintainer release process.

## License

Package code is licensed under [MIT](LICENSE). The bundled disposable-domain list
comes from [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
under [CC0](resources/disposable-email-domains.LICENSE).
