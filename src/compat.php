<?php

declare(strict_types=1);

// Backward-compatibility aliases for consumers migrating from overtrue/laravel-saml.
// Loaded via composer.json autoload.files so existing `use Overtrue\LaravelSaml\…;`
// statements resolve to the new namespace during the migration window.
//
// Planned for removal in v2.0. Update your imports to `Jdlien\LaravelSaml\…` at your leisure.

$aliases = [
    \Jdlien\LaravelSaml\Saml::class                                    => 'Overtrue\\LaravelSaml\\Saml',
    \Jdlien\LaravelSaml\SamlAuth::class                                => 'Overtrue\\LaravelSaml\\SamlAuth',
    \Jdlien\LaravelSaml\SamlUser::class                                => 'Overtrue\\LaravelSaml\\SamlUser',
    \Jdlien\LaravelSaml\SamlServiceProvider::class                     => 'Overtrue\\LaravelSaml\\SamlServiceProvider',
    \Jdlien\LaravelSaml\Utils::class                                   => 'Overtrue\\LaravelSaml\\Utils',
    \Jdlien\LaravelSaml\Exceptions\Exception::class                    => 'Overtrue\\LaravelSaml\\Exceptions\\Exception',
    \Jdlien\LaravelSaml\Exceptions\AssertException::class              => 'Overtrue\\LaravelSaml\\Exceptions\\AssertException',
    \Jdlien\LaravelSaml\Exceptions\InvalidConfigException::class       => 'Overtrue\\LaravelSaml\\Exceptions\\InvalidConfigException',
    \Jdlien\LaravelSaml\Exceptions\MethodNotFoundException::class      => 'Overtrue\\LaravelSaml\\Exceptions\\MethodNotFoundException',
    \Jdlien\LaravelSaml\Exceptions\UnauthenticatedException::class     => 'Overtrue\\LaravelSaml\\Exceptions\\UnauthenticatedException',
];

foreach ($aliases as $original => $legacy) {
    if (! class_exists($legacy, false) && ! interface_exists($legacy, false)) {
        class_alias($original, $legacy);
    }
}
