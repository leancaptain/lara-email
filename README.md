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

After the first tagged release is published on Packagist:

```sh
composer require leancaptain/lara-email
```

Before Packagist publication, add the Git repository to your application's
`composer.json` (the repository must be publicly readable):

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/leancaptain/lara-email"
        }
    ]
}
```

Then install the development branch:

```sh
composer require leancaptain/lara-email:dev-main
```

Use a tagged release for production once one is available.

## Usage

Add the rule to a Form Request or a call to Laravel's validator:

```php
use LeanCaptain\LaraEmail\Rules\PermanentEmailDomain;

public function rules(): array
{
    return [
        'email' => ['bail', 'required', 'string', 'email', new PermanentEmailDomain],
    ];
}
```

Laravel's `email` rule validates syntax. `bail` stops validation before the DNS
lookup if an earlier rule fails. Always pair `PermanentEmailDomain` with `email`;
the domain rule does not validate the complete email address.

The rule:

- Rejects `example.com`, `example.net`, `example.org`, and their subdomains.
- Rejects bundled disposable domains and their subdomains.
- Normalizes domain case and internationalized names to ASCII.
- Requires an MX record with a nonempty target other than `.` for the exact email domain.
- Rejects missing MX records, null MX records, and DNS lookup errors.

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
use LeanCaptain\LaraEmail\Rules\PermanentEmailDomain;

'email' => [
    'bail', 'required', 'string', 'email',
    Rule::when(app()->isProduction(), [new PermanentEmailDomain]),
],
```

### Validation messages

| Failure                                           | Message                                                                                       |
|---------------------------------------------------|-----------------------------------------------------------------------------------------------|
| Non-string input, missing `@`, or empty domain    | Please enter a valid email address.                                                           |
| Invalid IDN, example domain, or disposable domain | Please use a permanent email address. Example and disposable email addresses are not allowed. |
| DNS error or no usable MX record                  | Please use an email address with a domain that can receive email.                             |

### Limitations

DNS lookups are synchronous and are not cached by this package. Resolver latency
can slow validation, and temporary DNS failures cause validation to fail. Apply
the rule where a live domain check is appropriate, such as registration.

An MX record does not prove that a mailbox exists or belongs to the user, or that
its mail server is reachable. Require email verification in the application.
Domains that accept mail through A/AAAA fallback alone will fail this rule.

The disposable list is a bundled snapshot: newly created providers may not be
listed yet, and legitimate domains may occasionally be listed upstream. Existing
accounts are not changed. Report list corrections to the
[upstream project](https://github.com/disposable-email-domains/disposable-email-domains).

## Development and contributions

See [CONTRIBUTING.md](https://github.com/leancaptain/lara-email/blob/main/CONTRIBUTING.md) for setup, checks, local application testing,
and the release process. Report package bugs through
[GitHub issues](https://github.com/leancaptain/lara-email/issues).

```sh
git clone https://github.com/leancaptain/lara-email.git
cd lara-email
composer install
composer test
composer lint:check
composer analyse
```

Tests mock DNS and include standalone rule tests and Laravel validator integration.
CI runs on PHP 8.4 and 8.5 with native `intl` and with the IDN polyfill.

## Disposable-domain updates

From a source checkout, run:

```sh
composer update-domains
```

The updater downloads the list and CC0 license from the same upstream commit,
validates the data, and records the commit and SHA-256 checksum in
`resources/disposable-email-domains.json`. Review changes before release.

A weekly GitHub Actions workflow proposes updates through pull requests. Enable
"Allow GitHub Actions to create and approve pull requests" in the repository's
Actions settings for this workflow to open pull requests. The workflow does not
merge them or publish releases. Applications receive list changes when they update
this Composer package.

## License

Package code is licensed under [MIT](LICENSE). The bundled disposable-domain list
comes from [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
under [CC0](resources/disposable-email-domains.LICENSE).
