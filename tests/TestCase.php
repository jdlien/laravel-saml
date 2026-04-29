<?php

namespace Tests;

use Jdlien\LaravelSaml\SamlServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [SamlServiceProvider::class];
    }
}
