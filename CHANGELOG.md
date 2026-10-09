# Changelog

## 0.1.0

Initial release.

- Add the `UsableEmailDomain` Laravel validation rule, designed to accompany
  Laravel's `email` rule.
- Reject reserved example domains, bundled disposable domains, and their
  subdomains; normalize case and internationalized domain names.
- Require a usable MX record for the exact email domain. Reject missing and
  null MX records; report DNS lookup errors with a retry message.
- Support optional application cache stores for successful MX checks, bounded
  by the shortest MX TTL and capped at five minutes. Never cache failed checks.
- Include a disposable-domain snapshot with pinned upstream source metadata
  and its CC0 license.
- Support PHP 8.4 and 8.5 with Laravel / Illuminate Validation 13.
