# Contributing

Bug reports and focused pull requests are welcome. Include the PHP and Laravel
versions, a reproduction, and the expected behavior. Use placeholder addresses
instead of personal email addresses.

## Setup and checks

```sh
git clone https://github.com/leancaptain/lara-email.git
cd lara-email
composer install
composer validate --strict
composer lint:check
composer analyse
composer test
```

Use `composer lint` to fix formatting. Tests mock DNS: new rule tests should not
depend on public DNS or an external mail server. Keep runtime dependencies small
and preserve the distinction between Laravel's syntax validation and this
package's domain policy.

## Test in a local Laravel application

Add a path repository to the consuming application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../lara-email",
            "options": {
                "symlink": true,
                "versions": { "leancaptain/lara-email": "0.1.x-dev" }
            }
        }
    ]
}
```

Run `composer require leancaptain/lara-email:^0.1@dev`. Adjust the relative path
as needed. Replace the path repository with a published release before deploying
the application independently.

## Update the bundled list

Run `composer update-domains`, review all three changed resource files, and run
`composer test`. The updater requires outbound HTTPS access to GitHub. List
corrections belong in the upstream project; package releases distribute its
reviewed snapshots.

A weekly GitHub Actions workflow proposes list updates through pull requests.
Enable "Allow GitHub Actions to create and approve pull requests" in the
repository's Actions settings for this workflow to open pull requests. The
workflow does not merge them or publish releases.

## Release checklist for maintainers

1. Ensure `https://github.com/leancaptain/lara-email` is public and contains the
   package source on `main`.
2. Run the checks above and require the GitHub Actions matrix to pass on the
   commit being released. Review the bundled list's source metadata and licenses.
3. Create a semantic version tag, starting with `v0.1.0` for the initial release:

   ```sh
   git tag -a v0.1.0 -m 'Release v0.1.0'
   git push origin v0.1.0
   ```

4. For the first release, [submit the repository to Packagist](https://packagist.org/packages/submit)
   using its public HTTPS URL. Configure GitHub auto-updates through Packagist.
   Later versions are discovered from Git tags; do not add a `version` field to
   `composer.json`. See [Packagist's publishing instructions](https://packagist.org/about).
5. Verify `composer require leancaptain/lara-email:^0.1` in a clean Laravel 13
   application and publish release notes describing behavior and list changes.

Never move a published tag. Use a new version for corrections. Development tools,
tests, and workflows are excluded from distribution archives; runtime source,
domain resources, and both licenses must remain included.
