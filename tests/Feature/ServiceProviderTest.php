<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Jdlien\LaravelSaml\Saml;
use Jdlien\LaravelSaml\SamlServiceProvider;

covers(SamlServiceProvider::class);

it('registers itself with Laravel', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(SamlServiceProvider::class);
});

it('registers a saml-config publish group that exposes the package config file', function () {
    $paths = ServiceProvider::pathsToPublish(SamlServiceProvider::class, 'saml-config');

    expect($paths)->toBeArray()->not->toBeEmpty();

    $source = array_key_first($paths);
    expect(realpath($source))->toBe(realpath(__DIR__.'/../../config'))
        ->and(is_file($source.'/saml.php'))->toBeTrue();
});

it('merges the package default saml config under the saml key', function () {
    expect(\config('saml'))->toBeArray()
        ->and(\config('saml.sp'))->toBeArray()
        ->and(\config('saml.sp.entityId'))->not->toBeNull();
});

describe('compat shim class aliases', function () {
    it('aliases Overtrue\\LaravelSaml\\Saml to the new namespace', function () {
        expect(class_exists('Overtrue\\LaravelSaml\\Saml'))->toBeTrue()
            ->and((new ReflectionClass('Overtrue\\LaravelSaml\\Saml'))->getName())
            ->toBe(Saml::class);
    });

    it('aliases the SamlAuth, SamlUser, and Utils classes', function () {
        expect(class_exists('Overtrue\\LaravelSaml\\SamlAuth'))->toBeTrue()
            ->and(class_exists('Overtrue\\LaravelSaml\\SamlUser'))->toBeTrue()
            ->and(class_exists('Overtrue\\LaravelSaml\\Utils'))->toBeTrue();
    });

    it('aliases the SamlServiceProvider class', function () {
        expect(class_exists('Overtrue\\LaravelSaml\\SamlServiceProvider'))->toBeTrue();
    });

    it('aliases all Exception classes', function () {
        expect(class_exists('Overtrue\\LaravelSaml\\Exceptions\\Exception'))->toBeTrue()
            ->and(class_exists('Overtrue\\LaravelSaml\\Exceptions\\AssertException'))->toBeTrue()
            ->and(class_exists('Overtrue\\LaravelSaml\\Exceptions\\InvalidConfigException'))->toBeTrue()
            ->and(class_exists('Overtrue\\LaravelSaml\\Exceptions\\MethodNotFoundException'))->toBeTrue()
            ->and(class_exists('Overtrue\\LaravelSaml\\Exceptions\\UnauthenticatedException'))->toBeTrue();
    });
});

describe('IdP auto-configuration from config', function () {
    it('configures a resolver from config[idp] when present (covers register() branch)', function () {
        \config(['saml.idp' => [
            'entityId' => 'urn:test:idp',
            'singleSignOnService' => ['url' => 'https://idp.example.com/sso'],
        ]]);

        // Re-invoke the provider's register() so the auto-config branch fires.
        $provider = new SamlServiceProvider($this->app);
        $provider->register();

        $reflection = new ReflectionClass(Saml::class);
        $resolver = $reflection->getProperty('idpConfigResolver');
        $resolver->setAccessible(true);

        expect($resolver->getValue())->not->toBeNull();

        // And the resolver should yield the configured idp array.
        $closure = $resolver->getValue();
        expect($closure('default'))->toMatchArray(['entityId' => 'urn:test:idp']);
    });
});
