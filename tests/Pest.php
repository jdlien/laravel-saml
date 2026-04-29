<?php

declare(strict_types=1);

use Jdlien\LaravelSaml\Saml;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| All Unit, Feature, and Integration suites extend the package TestCase,
| which boots Orchestra Testbench so Laravel helpers (\request(), \config(),
| facades) are available.
|
*/

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Global Hooks
|--------------------------------------------------------------------------
|
| beforeEach: clears the static SamlAuth cache and resets the IdP resolver
| so test state never bleeds between examples.
| afterEach: closes Mockery to silence PHPUnit 12's "risky" warnings.
|
*/

beforeEach(function (): void {
    Saml::flushResolvedIdps();

    // The resolver closure is package-static and not exposed for direct reset.
    // PHP 8.3+ ReflectionProperty::setValue() takes a single arg for static
    // properties (the legacy two-arg form was deprecated).
    $reflection = new ReflectionClass(Saml::class);
    $resolver = $reflection->getProperty('idpConfigResolver');
    $resolver->setAccessible(true);
    $resolver->setValue(null);
});

afterEach(function (): void {
    Mockery::close();
});
