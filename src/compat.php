<?php

declare(strict_types=1);
use Jdlien\LaravelSaml\Exceptions\AssertException;
use Jdlien\LaravelSaml\Exceptions\Exception;
use Jdlien\LaravelSaml\Exceptions\InvalidConfigException;
use Jdlien\LaravelSaml\Exceptions\MethodNotFoundException;
use Jdlien\LaravelSaml\Exceptions\UnauthenticatedException;
use Jdlien\LaravelSaml\Saml;
use Jdlien\LaravelSaml\SamlAuth;
use Jdlien\LaravelSaml\SamlServiceProvider;
use Jdlien\LaravelSaml\SamlUser;
use Jdlien\LaravelSaml\Utils;

// Backward-compatibility aliases for consumers migrating from overtrue/laravel-saml.
// Loaded via composer.json autoload.files so existing `use Overtrue\LaravelSaml\…;`
// statements resolve to the new namespace during the migration window.
//
// Planned for removal in v2.0. Update your imports to `Jdlien\LaravelSaml\…` at your leisure.

$aliases = [
    Saml::class => 'Overtrue\\LaravelSaml\\Saml',
    SamlAuth::class => 'Overtrue\\LaravelSaml\\SamlAuth',
    SamlUser::class => 'Overtrue\\LaravelSaml\\SamlUser',
    SamlServiceProvider::class => 'Overtrue\\LaravelSaml\\SamlServiceProvider',
    Utils::class => 'Overtrue\\LaravelSaml\\Utils',
    Exception::class => 'Overtrue\\LaravelSaml\\Exceptions\\Exception',
    AssertException::class => 'Overtrue\\LaravelSaml\\Exceptions\\AssertException',
    InvalidConfigException::class => 'Overtrue\\LaravelSaml\\Exceptions\\InvalidConfigException',
    MethodNotFoundException::class => 'Overtrue\\LaravelSaml\\Exceptions\\MethodNotFoundException',
    UnauthenticatedException::class => 'Overtrue\\LaravelSaml\\Exceptions\\UnauthenticatedException',
];

foreach ($aliases as $original => $legacy) {
    if (! class_exists($legacy, false) && ! interface_exists($legacy, false)) {
        class_alias($original, $legacy);
    }
}
