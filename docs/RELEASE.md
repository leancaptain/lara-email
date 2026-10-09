# v0.1.0 release preparation

The source and consumer documentation have been reviewed locally. Publication
remains a maintainer action; no release tag has been created or pushed.

## Verification completed

- Composer metadata validation, formatting, and PHPStan analysis passed.
- All 47 tests (131 assertions) passed on PHP 8.4 with native `intl` and with
  native `intl` disabled to exercise the IDN polyfill.
- Composer's locked dependency audit reported no security advisories.
- The distribution archive contains the renamed rule, Composer metadata,
  README, changelog, domain resources, and MIT/CC0 licenses. Tests, developer
  tools, workflows, and internal planning documents are excluded.
- An isolated consumer installed the extracted distribution as version 0.1.0
  with Illuminate Validation and Cache 13, without this repository's development
  dependencies. Integration checks passed on PHP 8.4 and 8.5 for validation,
  domain blocking, cache hits, and recovery after uncached DNS errors.

The full PHP 8.5 test suite could not run locally because that runtime lacks DOM
and other test extensions. The existing GitHub Actions matrix must pass for the
release commit on PHP 8.4 and 8.5, with native `intl` and the polyfill. DNS checks
in automated tests are mocked; public Packagist installation is verified after
publication.

## Publish

1. Review `git diff` and `git status`, then commit the release files:

   ```sh
   git add .gitattributes CONTRIBUTING.md README.md CHANGELOG.md composer.json src/Rules tests docs
   git commit -m "Prepare v0.1.0 release"
   git push origin main
   ```

2. Wait for all four GitHub Actions test jobs to pass on that commit.
3. Make `leancaptain/lara-email` public in GitHub repository settings.
4. Tag the verified commit and push the tag:

   ```sh
   git tag -a v0.1.0 -m "Release v0.1.0"
   git push origin v0.1.0
   ```

   Never move a published tag. Create GitHub release notes from `CHANGELOG.md`.
5. [Submit the public repository to Packagist](https://packagist.org/packages/submit)
   using `https://github.com/leancaptain/lara-email`. Configure GitHub auto-updates
   and confirm that version `v0.1.0` appears. See the
   [official publishing instructions](https://packagist.org/about).
6. In a clean Laravel 13 application using PHP 8.4 or 8.5, verify public installation:

   ```sh
   composer require leancaptain/lara-email
   composer show leancaptain/lara-email
   ```

   Confirm installation of 0.1.0 and apply the README example to a validator.
   Check a known disposable address and an address at a domain you control with
   usable MX records. DNS checks require network access from the application.

The optional cache remains disabled unless the application supplies a cache
store. Strict MX validation remains the default. DNS results do not establish
mailbox ownership; applications should require email verification.
