# Future improvements

The initial release keeps strict MX validation, optional successful-result
caching, and a bundled disposable-domain list. These ideas are future work, not
supported features or release commitments.

- **Resolver timeouts:** Evaluate an injectable resolver with explicit timeout
  control and reliable distinctions between nonexistent domains and temporary
  DNS errors. Keep dependencies small and tests independent of live DNS.
- **Application domain overrides:** Consider explicit allow/block lists for
  upstream classification mistakes or application policy. An allow override
  should bypass only disposable classification, retaining syntax validation,
  reserved example-domain rejection, and MX checks.
- **Optional SMTP fallback:** Consider opt-in A/AAAA fallback only when MX is
  absent. Explicit null MX and DNS errors must still fail. Keep strict MX as the
  default and document that address records do not prove a mail server exists.
- **Localized messages:** Consider stable translation keys and application
  overrides without requiring a service provider for basic use.

Mailbox ownership belongs in the consuming application's email-verification
flow. A DNS rule cannot guarantee mailbox existence or delivery. Continue
reviewing automated upstream list updates and distributing them through releases.
