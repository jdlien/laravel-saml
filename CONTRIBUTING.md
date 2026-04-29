# Contributing

Thanks for your interest in improving `jdlien/laravel-saml`. This package is a Laravel binding around the [SAML-Toolkits PHP toolkit](https://github.com/SAML-Toolkits/php-saml) — protocol-level SAML logic belongs upstream there, not here.

## Setup

```bash
git clone https://github.com/jdlien/laravel-saml.git
cd laravel-saml
composer install
```

PHP 8.3+ is required. The repo is Laravel-Herd-friendly on macOS — `php` and `composer` should resolve out of the box.

## Running tests

```bash
composer test                # Pest suite
composer test:coverage       # with --min=95 enforced
composer analyse             # Larastan / PHPStan
composer check-style         # Pint dry-run
composer fix-style           # Pint auto-fix
```

For coverage, you'll need xdebug or pcov. With Laravel Herd, prefix the command:

```bash
herd coverage vendor/bin/pest --coverage
```

## Pull requests

- Match the existing code style — `composer fix-style` will handle most of it.
- Keep coverage at or above 95%. Add tests for any new behavior.
- For bug fixes, include a regression test that fails without your fix.
- Don't add net-new features that fall outside "Laravel binding around onelogin/php-saml" — protocol additions belong upstream.
- Keep PRs focused. Refactors and feature work should be separate from bug fixes.

## What's in scope

- Bug fixes in the binding layer (the thin wrapper around `onelogin/php-saml`).
- Laravel-version-bump support (constraint widening + matrix updates).
- DX improvements: better error messages, type hints, additional safety checks at the binding boundary.
- Documentation, examples, security notes.

## What's out of scope

- Reimplementing SAML 2.0 protocol behavior. File those upstream at [SAML-Toolkits/php-saml](https://github.com/SAML-Toolkits/php-saml).
- Bundling another SAML backend (LightSAML, simplesamlphp). The OneLogin toolkit is the chosen engine.

## Reporting security issues

If you find a security issue, please report it privately rather than opening a public GitHub issue. Email `jd@jdlien.com` with details.
