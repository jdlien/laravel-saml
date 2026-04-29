# Changelog

All notable changes to `jdlien/laravel-saml` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] — 2026-04-29

First public release as `jdlien/laravel-saml`. Bumped to 2.0.0 (rather than reusing the inherited 1.x line) so Composer's SemVer resolver actually serves the new code: the upstream tags `1.0.0`–`1.2.0` are preserved on the repo as historical lineage from `overtrue/laravel-saml` but represent the pre-fork codebase.

### Lineage

Forked from [`overtrue/laravel-saml`](https://github.com/overtrue/laravel-saml) at `c600f90` (the L12 release). Modernized, hardened, and re-published under a new vendor namespace.

### Added

- Laravel 13 support; baseline raised to Laravel 12+ / PHP 8.3+.
- `Saml::flushResolvedIdps()` test/Octane helper. Drops the static `SamlAuth` cache so subsequent `Saml::idp()` calls re-resolve through any newly-registered closure.
- `SamlUser::getRawRelayState()` escape hatch for callers who explicitly need the unvalidated RelayState value.
- Compat shim at `src/compat.php` (auto-loaded via composer `files`) registers `class_alias` for every `Overtrue\LaravelSaml\…` symbol so existing imports keep working during migration. Planned for removal in v3.0.
- Pest 4 test suite covering every public method on every `src/` class. 67 tests, 100% line coverage, `--min=95` enforced in CI.
- Larastan/PHPStan at level 6, clean.
- CI matrix: PHP 8.3/8.4 × Laravel 12/13, plus separate static-analysis, Pint style, and Pest mutation (informational) jobs.

### Changed

- Vendor namespace: `Overtrue\LaravelSaml\…` → `Jdlien\LaravelSaml\…`.
- Composer package name: `overtrue/laravel-saml` → `jdlien/laravel-saml`.
- `onelogin/php-saml` floor raised to `^4.3.1` so consumers automatically pick up the [CVE-2025-66475](https://github.com/SAML-Toolkits/php-saml/security/advisories) (xmlseclibs) fix.
- `composer.json` now requires `ext-dom`, `ext-libxml`, and `ext-zlib` explicitly. Upstream `onelogin/php-saml` lists them only as `suggest`, but core code paths (XML loading, `gzdeflate`/`gzinflate` request compression) require them; consumers on stripped PHP builds previously got runtime fatals.
- `composer.json` declares `conflict` with `overtrue/laravel-saml` to prevent dual installation, which otherwise produces duplicate service-provider registration via Laravel auto-discovery and namespace clashes via the compat shim.
- `SamlAuth::handleLogoutRequest()` return type narrowed to `?RedirectResponse`. SP-initiated logout returns `null`; IdP-initiated logout returns a redirect response back to the IdP.
- README now documents middleware requirements for SAML routes: session middleware (the package reads/writes `saml.authnRequestId` and `saml.logoutRequestId`) and CSRF exclusion for `POST /acs` (since IdP POSTs do not include a Laravel CSRF token).
- `Saml::configureIdpUsing()` now also flushes the resolved-IdP cache so the new resolver takes effect on the next `Saml::idp()` call (previously a footgun).
- `SamlUser::getIntendedUrl()` now validates RelayState origin: relative paths and same-host absolute URLs are returned; cross-origin, protocol-relative, non-HTTP-scheme, and malformed values return `null`.
- `SamlUser::parseAttributes($attributes)` honors the supplied `$attributes` array values instead of re-fetching each via `getAttribute()`.
- Test framework: PHPUnit 9 → Pest 4 (PHPUnit 12 transitively).
- Code style: php-cs-fixer → Laravel Pint (default config).
- Removed `brainmaestro/composer-git-hooks` and all `cghooks` post-install/update scripts — opt-out of opinionated commit hooks.

### Fixed

- `Saml::idp($name, $settings)` operator-precedence bug. Previously, passing explicit `$settings` would still invoke the registered resolver due to ternary-vs-null-coalescing precedence. Now `$settings` short-circuits the resolver as documented.
- `Saml::getMetadataXML()` now calls `normalizeConfig()` before constructing OneLogin `Settings`, so file-path cert/key entries are inlined as expected. Previously, file paths could end up embedded in the metadata XML verbatim, which then failed XSD validation downstream.
- `Saml::normalizeConfig()` no longer errors when `idp.x509cert` is absent; previously it would emit an undefined-index notice.
- `Saml::normalizeConfig()` `file_exists()` calls are now guarded against PHP 8.3+ `ValueError` on long inputs and against accidental matches on inlined PEM strings (which contain newlines).
- `SamlAuth::__call()` uses `method_exists()` instead of `is_callable()`. The latter returned true for any name on a target with `__call()`, silently swallowing typos; the former correctly lets `MethodNotFoundException` fire.
- `Utils::loadKeyFromFile()` and `loadCertFromFile()` validate `is_readable()` before calling OpenSSL helpers and suppress the underlying warning so the typed `InvalidConfigException` is the user-visible error (not Laravel's converted `ErrorException`).
- `Utils::extractOpensslString()` `preg_quote`s the delimiter so a delimiter containing regex metacharacters can't break the match.
- `AssertException` and `UnauthenticatedException` constructors accept `?\Throwable` instead of `?\Exception`. Callers throw from `catch (\Throwable $e)`; the narrower type would have failed at runtime if OneLogin internals threw an `Error` (not an `Exception`).
- `SamlAuth::handleLogoutRequest()` no longer fatally crashes when `OneLogin\Saml2\Auth::processSLO()` returns `null` (the SP-initiated `LogoutResponse` path). The previous empty-string-only guard fell through into `new RedirectResponse(null)` → `TypeError`.
- `config/saml.php` typo: the IdP `entityId` template referenced `SAML_IDP_PRIVATEKEY` env var instead of `SAML_IDP_ENTITYID`.
- README example used `Auth::set($user)` (not a real method) → corrected to `Auth::login($user)`.

### Removed

- Sponsor badges, JetBrains attribution, Chinese-language sections, and the `FUNDING.yml` from the upstream README.
- `JetBrains\PhpStorm\Pure` attribute usage on methods/constructors with side effects.
- `SamlAuth::__construct()`'s second `?Request $request` parameter and the corresponding `protected Request $request` property. Both were unused and undocumented surface area.
- Empty `tests/Integration/` test suite and unused `tests/User.php` / `tests/migrations/` / `tests/database/` scaffolding inherited from upstream.

## Pre-1.0 history

The git log preserves overtrue's commits prior to the fork. See [`overtrue/laravel-saml`](https://github.com/overtrue/laravel-saml) for the full upstream history.
