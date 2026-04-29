<?php

declare(strict_types=1);

namespace Jdlien\LaravelSaml;

use Illuminate\Support\ServiceProvider;

class SamlServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            \dirname(__DIR__).'/config/' => config_path(),
        ], 'saml-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(\dirname(__DIR__).'/config/saml.php', 'saml');

        $config = \config('saml');

        if (! empty($config['idp'])) {
            Saml::configureIdpUsing(fn () => $config['idp']);
        }
    }
}
