<?php

declare(strict_types=1);

use Jdlien\LaravelSaml\Exceptions\InvalidConfigException;
use Jdlien\LaravelSaml\Saml;
use Jdlien\LaravelSaml\SamlAuth;
use OneLogin\Saml2\Auth;

covers(Saml::class);

$fixtureDir = dirname(__DIR__).'/fixtures';

/**
 * Build a minimum-viable SAML config that OneLogin Auth will accept.
 *
 * @return array<string, mixed>
 */
function validSamlConfig(string $fixtureDir): array
{
    return [
        'strict' => false,
        'debug' => false,
        'sp' => [
            'entityId' => 'urn:test:sp',
            'assertionConsumerService' => [
                'url' => 'https://sp.example.com/saml/acs',
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            ],
            'singleLogoutService' => [
                'url' => 'https://sp.example.com/saml/sls',
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'x509cert' => $fixtureDir.'/test.crt',
            'privateKey' => $fixtureDir.'/test.key',
        ],
        'idp' => [
            'entityId' => 'urn:test:idp',
            'singleSignOnService' => [
                'url' => 'https://idp.example.com/saml/sso',
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'singleLogoutService' => [
                'url' => 'https://idp.example.com/saml/slo',
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'x509cert' => $fixtureDir.'/test.crt',
        ],
    ];
}

describe('configureIdpUsing', function () use ($fixtureDir) {
    it('stores the resolver and flushes any previously cached SamlAuth', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        // Prime the cache via an explicit settings call.
        $first = Saml::idp('default', validSamlConfig($fixtureDir)['idp']);

        $resolverInvoked = 0;
        Saml::configureIdpUsing(function (string $name) use (&$resolverInvoked, $fixtureDir): array {
            $resolverInvoked++;

            return validSamlConfig($fixtureDir)['idp'];
        });

        // The previous cache entry should have been flushed by configureIdpUsing.
        $second = Saml::idp('default');

        expect($resolverInvoked)->toBe(1)
            ->and($second)->not->toBe($first);
    });
});

describe('idp() argument precedence (regression)', function () use ($fixtureDir) {
    it('uses explicit $settings without invoking the resolver closure', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        $resolverCalled = false;
        Saml::configureIdpUsing(function () use (&$resolverCalled): array {
            $resolverCalled = true;

            return ['this' => 'should not be reached'];
        });

        $samlAuth = Saml::idp('explicit', validSamlConfig($fixtureDir)['idp']);

        expect($resolverCalled)->toBeFalse()
            ->and($samlAuth)->toBeInstanceOf(SamlAuth::class);
    });

    it('falls back to the resolver only when $settings is null', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        $resolverCalled = false;
        Saml::configureIdpUsing(function () use (&$resolverCalled, $fixtureDir): array {
            $resolverCalled = true;

            return validSamlConfig($fixtureDir)['idp'];
        });

        Saml::idp('from-resolver');

        expect($resolverCalled)->toBeTrue();
    });
});

describe('idp() error paths', function () {
    it('throws InvalidConfigException when neither $settings nor a resolver yields config', function () {
        \config(['saml' => []]);
        // A resolver that yields null is semantically equivalent to no resolver.
        Saml::configureIdpUsing(fn () => null);

        Saml::idp('orphan');
    })->throws(InvalidConfigException::class, 'Cannot resolve idp config from resolver.');
});

describe('normalizeConfig', function () use ($fixtureDir) {
    it('returns the merged config when SP and IdP are valid', function () use ($fixtureDir) {
        $normalized = Saml::normalizeConfig(validSamlConfig($fixtureDir));

        expect($normalized['sp']['entityId'])->toBe('urn:test:sp');
    });

    it('inlines a private key file from disk', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        $normalized = Saml::normalizeConfig($config);

        // After normalizeConfig, the privateKey should be the base64 body — not the path.
        expect($normalized['sp']['privateKey'])
            ->not->toBe($fixtureDir.'/test.key')
            ->and($normalized['sp']['privateKey'])->toBeString();
    });

    it('inlines an SP X509 certificate file from disk', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        $normalized = Saml::normalizeConfig($config);

        expect($normalized['sp']['x509cert'])
            ->not->toBe($fixtureDir.'/test.crt')
            ->and($normalized['sp']['x509cert'])->toBeString();
    });

    it('inlines an IdP X509 certificate file from disk', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        $normalized = Saml::normalizeConfig($config);

        expect($normalized['idp']['x509cert'])
            ->not->toBe($fixtureDir.'/test.crt')
            ->and($normalized['idp']['x509cert'])->toBeString();
    });

    it('leaves inlined PEM strings untouched (does not call file_exists on them)', function () use ($fixtureDir) {
        $pem = "-----BEGIN PRIVATE KEY-----\nABC\n-----END PRIVATE KEY-----";
        $config = validSamlConfig($fixtureDir);
        $config['sp']['privateKey'] = $pem;

        $normalized = Saml::normalizeConfig($config);

        expect($normalized['sp']['privateKey'])->toBe($pem);
    });

    it('tolerates a missing idp.x509cert key (does not error on undefined index)', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        unset($config['idp']['x509cert']);

        $normalized = Saml::normalizeConfig($config);

        expect($normalized['idp'])->not->toHaveKey('x509cert');
    });

    it('throws when sp.entityId is missing', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        unset($config['sp']['entityId']);

        Saml::normalizeConfig($config);
    })->throws(InvalidConfigException::class, 'saml.sp.entityId');

    it('throws when sp.assertionConsumerService.url is missing', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        unset($config['sp']['assertionConsumerService']['url']);

        Saml::normalizeConfig($config);
    })->throws(InvalidConfigException::class, 'assertionConsumerService.url');

    it('throws when sp.singleLogoutService is set but missing url', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        unset($config['sp']['singleLogoutService']['url']);

        Saml::normalizeConfig($config);
    })->throws(InvalidConfigException::class, 'singleLogoutService.url');

    it('resolves a relative cert path against base_path()', function () use ($fixtureDir) {
        // Place a fixture under base_path() so we can reference it relatively.
        $relative = 'saml-test-fixture.crt';
        $absolute = base_path($relative);
        copy($fixtureDir.'/test.crt', $absolute);

        try {
            $config = validSamlConfig($fixtureDir);
            $config['sp']['x509cert'] = $relative;

            $normalized = Saml::normalizeConfig($config);

            expect($normalized['sp']['x509cert'])
                ->not->toBe($relative)
                ->and($normalized['sp']['x509cert'])->toBeString();
        } finally {
            @unlink($absolute);
        }
    });

    it('throws InvalidConfigException when sp.privateKey path resolves nowhere', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        $config['sp']['privateKey'] = 'storage/certs/missing.key';

        Saml::normalizeConfig($config);
    })->throws(InvalidConfigException::class, 'sp.privateKey');

    it('throws InvalidConfigException when sp.x509cert path resolves nowhere', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        $config['sp']['x509cert'] = 'storage/certs/missing.crt';

        Saml::normalizeConfig($config);
    })->throws(InvalidConfigException::class, 'sp.x509cert');

    it('throws InvalidConfigException when idp.x509cert path resolves nowhere', function () use ($fixtureDir) {
        $config = validSamlConfig($fixtureDir);
        $config['idp']['x509cert'] = 'storage/certs/missing-idp.crt';

        Saml::normalizeConfig($config);
    })->throws(InvalidConfigException::class, 'idp.x509cert');
});

describe('flushResolvedIdps', function () use ($fixtureDir) {
    it('clears cached SamlAuth instances so the next idp() rebuilds', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        $first = Saml::idp('default', validSamlConfig($fixtureDir)['idp']);
        Saml::flushResolvedIdps();
        $second = Saml::idp('default', validSamlConfig($fixtureDir)['idp']);

        expect($second)->not->toBe($first);
    });
});

describe('getMetadataXML', function () use ($fixtureDir) {
    it('returns valid XML metadata as an HTTP response', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        $response = Saml::getMetadataXML();

        expect($response->headers->get('Content-Type'))->toBe('text/xml')
            ->and($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toContain('EntityDescriptor')
            ->and($response->getContent())->toContain('urn:test:sp');
    });

    it('wraps OneLogin failures as InvalidConfigException', function () {
        \config(['saml' => ['sp' => ['entityId' => null]]]);

        Saml::getMetadataXML();
    })->throws(InvalidConfigException::class);
});

describe('getMetadataXMLAsStreamResponse', function () use ($fixtureDir) {
    it('returns a streamed download response with a generated filename', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir), 'app.name' => 'jdlien-app']);

        $response = Saml::getMetadataXMLAsStreamResponse();

        expect($response->headers->get('Content-Disposition'))->toContain('jdlien-app-metadata.xml');
    });

    it('uses an explicit filename when provided', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir), 'app.name' => 'jdlien-app']);

        $response = Saml::getMetadataXMLAsStreamResponse('custom.xml');

        expect($response->headers->get('Content-Disposition'))->toContain('custom.xml');
    });

    it('streams the metadata XML body when the response is sent', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        $response = Saml::getMetadataXMLAsStreamResponse('metadata.xml');

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        expect($body)->toContain('EntityDescriptor');
    });
});

describe('__callStatic', function () use ($fixtureDir) {
    it('forwards static calls to the default IdP', function () use ($fixtureDir) {
        \config(['saml' => validSamlConfig($fixtureDir)]);

        Saml::configureIdpUsing(fn () => validSamlConfig($fixtureDir)['idp']);

        // getAuth() is a real method on SamlAuth; this proves __callStatic resolves
        // and forwards to the cached default IdP.
        expect(Saml::getAuth())->toBeInstanceOf(Auth::class);
    });
});
