# Laravel SAML

[![Packagist Version](https://img.shields.io/packagist/v/jdlien/laravel-saml.svg?v=2)](https://packagist.org/packages/jdlien/laravel-saml)
[![Total Downloads](https://img.shields.io/packagist/dt/jdlien/laravel-saml.svg?v=2)](https://packagist.org/packages/jdlien/laravel-saml)
[![License](https://img.shields.io/packagist/l/jdlien/laravel-saml.svg?v=2)](https://packagist.org/packages/jdlien/laravel-saml)

A SAML 2.0 toolkit for Laravel, built around [SAML-Toolkits/php-saml](https://github.com/SAML-Toolkits/php-saml) (on packagist as `onelogin/php-saml`).

## Requirements

- PHP `^8.3`
- Laravel `^12.0` or `^13.0`
- `ext-openssl`

## Installation

```bash
composer require jdlien/laravel-saml
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=saml-config
```

This creates `config/saml.php`. The shape mirrors the [OneLogin PHP toolkit settings](https://github.com/SAML-Toolkits/php-saml#settings); see that project's docs for advanced options.

## Configuration

### Single IdP

If your application authenticates against a single IdP, fill in the `idp` section of `config/saml.php` (or supply the corresponding `SAML_IDP_*` env vars). The package auto-registers the resolver on boot.

### Multiple IdPs

For multi-IdP scenarios, leave `idp` unset in `config/saml.php` and register a resolver from a service provider:

```php
use Jdlien\LaravelSaml\Saml;

Saml::configureIdpUsing(function (string $idpName): array {
    // Look up the idp config from your DB, tenant store, etc.
    return [
        'entityId' => '...',
        'singleSignOnService' => ['url' => '...'],
        // ... see config/saml.php for the full shape
    ];
});
```

Calling `Saml::idp($name)->redirect()` resolves through the closure and caches the resulting `SamlAuth` instance.

## Usage

For multi-IdP scenarios, swap any `Saml::method()` call below for `Saml::idp($name)->method()` to target a specific IdP.

### Controller Scaffold

```bash
php artisan make:controller SamlController
```

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jdlien\LaravelSaml\Saml;
use App\Models\User;

class SamlController extends Controller
{
    public function login() {}
    public function acs() {}
    public function logout() {}
    public function sls() {}
    public function metadata() {}
}
```

### Routes

| Method | URI                       | Name            |
| ------ | ------------------------- | --------------- |
| GET    | `{routesPrefix}/login`    | `saml.login`    |
| POST   | `{routesPrefix}/acs`      | `saml.acs`      |
| GET    | `{routesPrefix}/logout`   | `saml.logout`   |
| GET    | `{routesPrefix}/sls`      | `saml.sls`      |
| GET    | `{routesPrefix}/metadata` | `saml.metadata` |

```php
use App\Http\Controllers\SamlController;

Route::get('saml/login', [SamlController::class, 'login'])->name('saml.login');
Route::post('saml/acs', [SamlController::class, 'acs'])->name('saml.acs');
Route::get('saml/logout', [SamlController::class, 'logout'])->name('saml.logout');
Route::get('saml/sls', [SamlController::class, 'sls'])->name('saml.sls');
Route::get('saml/metadata', [SamlController::class, 'metadata'])->name('saml.metadata');
```

### Middleware Requirements

- The SAML routes must run under session middleware (typically the `web` group). The package reads/writes `saml.authnRequestId` and `saml.logoutRequestId` on the session to correlate requests with responses.
- The `POST /acs` route must be excluded from CSRF validation, because the IdP's POST will not include a Laravel CSRF token. In Laravel 11+/12+:

  ```php
  // bootstrap/app.php
  ->withMiddleware(function (Middleware $middleware) {
      $middleware->validateCsrfTokens(except: [
          'saml/acs',
      ]);
  })
  ```

  In older apps, add the same path to `App\Http\Middleware\VerifyCsrfToken::$except`.
- If you also expose a POST SLS endpoint for an IdP that uses HTTP-POST binding for SLO, exclude that route too.

### Redirect to the IdP Login

Initiates SSO.

```php
public function login(Request $request)
{
    return Saml::redirect();
}
```

### Assertion Consumer Service (ACS)

Handles the IdP's authentication response. Returns a `SamlUser` (which wraps the OneLogin `Auth` object plus convenience accessors).

```php
public function acs(Request $request)
{
    $samlUser = Saml::getAuthenticatedUser();

    $user = User::firstOrCreate(['email' => $samlUser->getUserId()]);
    Auth::login($user);

    // getIntendedUrl() validates the SAML RelayState — see "Security" below.
    return redirect($samlUser->getIntendedUrl() ?? '/home');
}
```

### Redirect to IdP Logout

```php
public function logout(Request $request)
{
    return Saml::redirectToLogout();
}
```

The IdP returns a Logout Response through the user's browser to your `/sls` endpoint.

### Single Logout Service (SLS)

Handles both Logout Responses (SP-initiated logout) and Logout Requests (IdP-initiated logout).

```php
public function sls(Request $request)
{
    $redirect = Saml::handleLogoutRequest();

    Auth::logout();

    // IdP-initiated logout: handleLogoutRequest() returns a RedirectResponse
    // that sends a LogoutResponse back to the IdP. Honor it.
    return $redirect ?? redirect('/');
}
```

### Metadata Endpoint

Publishes the SP metadata XML so the IdP can register your service.

```php
public function metadata(Request $request)
{
    return Saml::getMetadataXML();

    // Or as a streamed download:
    // return Saml::getMetadataXMLAsStreamResponse('my-app-saml-metadata.xml');
}
```

## Security

### RelayState Validation

`SamlUser::getIntendedUrl()` is the safe accessor for the SAML RelayState — it validates the value against open-redirect attacks. It returns:

- relative paths (e.g. `/dashboard`) as-is
- absolute URLs only when the host matches the application host

It returns `null` for cross-origin URLs, protocol-relative URLs (`//example.com/...`), `javascript:` / `data:` / other non-HTTP schemes, and anything malformed. **Always prefer `getIntendedUrl()` over the raw RelayState** when redirecting users after login.

If you have a legitimate reason to inspect the unvalidated value (e.g. logging, custom validation), use `getRawRelayState()` — but treat its output as user-controlled input.

### Underlying SAML Implementation

The actual SAML 2.0 protocol logic — signature validation, XML canonicalization, encrypted assertion handling, etc. — lives in [`onelogin/php-saml`](https://github.com/SAML-Toolkits/php-saml). This package's job is the Laravel binding; it intentionally doesn't reimplement protocol primitives.

## Migrating from `overtrue/laravel-saml`

This package is a successor to `overtrue/laravel-saml`. Migration is intentionally cheap:

1. **Update `composer.json`:**

   ```diff
   -    "overtrue/laravel-saml": "^1.2",
   +    "jdlien/laravel-saml": "^2.0",
   ```

   Then `composer update jdlien/laravel-saml`.

2. **Update the service provider reference** if you registered it manually (auto-discovered installs need no change). In `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

   ```diff
   -    \Overtrue\LaravelSaml\SamlServiceProvider::class,
   +    \Jdlien\LaravelSaml\SamlServiceProvider::class,
   ```

3. **Existing imports keep working** — a compat shim aliases every `Overtrue\LaravelSaml\…` class name to its new home. You can update `use` statements at your leisure. The compat shim will be removed in v3.0.

4. **Facade calls (`Saml::redirect()`, etc.) need no changes.** The facade name is preserved.

See [CHANGELOG.md](CHANGELOG.md) for behavior changes that may affect existing consumers.

## Testing

```bash
composer install
composer test
composer test:coverage
composer analyse
composer check-style
composer fix-style
```

## License

MIT. See [LICENSE](LICENSE).

> Originally based on [`overtrue/laravel-saml`](https://github.com/overtrue/laravel-saml) by [@overtrue](https://github.com/overtrue). Now maintained as an independent package by [@jdlien](https://github.com/jdlien) — modernized for Laravel 12/13 and PHP 8.3+, with bug fixes, security hardening, and full Pest 4 test coverage.
