<?php

declare(strict_types=1);

use Jdlien\LaravelSaml\Exceptions\InvalidConfigException;
use Jdlien\LaravelSaml\Utils;

$fixtureDir = dirname(__DIR__).'/fixtures';

it('loads a private key from a PEM file and returns the base64 body', function () use ($fixtureDir) {
    $loaded = Utils::loadKeyFromFile($fixtureDir.'/test.key');

    expect($loaded)
        ->toBeString()
        ->not->toBe('')
        ->not->toContain('BEGIN PRIVATE KEY')
        ->not->toContain('END PRIVATE KEY');
});

it('loads an X509 certificate from a PEM file and returns the base64 body', function () use ($fixtureDir) {
    $loaded = Utils::loadCertFromFile($fixtureDir.'/test.crt');

    expect($loaded)
        ->toBeString()
        ->not->toBe('')
        ->not->toContain('BEGIN CERTIFICATE')
        ->not->toContain('END CERTIFICATE');
});

it('throws InvalidConfigException when the private key path is missing', function () {
    Utils::loadKeyFromFile('/nonexistent/path/to/key.pem');
})->throws(InvalidConfigException::class, 'private key');

it('throws InvalidConfigException when the certificate path is missing', function () {
    Utils::loadCertFromFile('/nonexistent/path/to/cert.pem');
})->throws(InvalidConfigException::class, 'X509 certificate');

it('throws InvalidConfigException when the file is not parseable as a private key', function () use ($fixtureDir) {
    $bogus = $fixtureDir.'/bogus.key';
    file_put_contents($bogus, "not a pem file\n");

    try {
        Utils::loadKeyFromFile($bogus);
    } finally {
        @unlink($bogus);
    }
})->throws(InvalidConfigException::class, 'Could not parse private key');

it('throws InvalidConfigException when the file is not parseable as a certificate', function () use ($fixtureDir) {
    $bogus = $fixtureDir.'/bogus.crt';
    file_put_contents($bogus, "not a pem file\n");

    try {
        Utils::loadCertFromFile($bogus);
    } finally {
        @unlink($bogus);
    }
})->throws(InvalidConfigException::class, 'Could not parse X509 certificate');

it('extracts the body of a PEM-formatted string', function () {
    $pem = "-----BEGIN CERTIFICATE-----\nABCDEF123456\n-----END CERTIFICATE-----";

    expect(Utils::extractOpensslString($pem, 'CERTIFICATE'))->toBe('ABCDEF123456');
});

it('returns an empty string when the delimiter is not found', function () {
    $pem = '-----BEGIN PRIVATE KEY-----ABC-----END PRIVATE KEY-----';

    expect(Utils::extractOpensslString($pem, 'CERTIFICATE'))->toBe('');
});

it('quotes regex metacharacters in the delimiter so they cannot break the match', function () {
    $pem = "-----BEGIN X.509 CERT-----\nABCDEF\n-----END X.509 CERT-----";

    expect(Utils::extractOpensslString($pem, 'X.509 CERT'))->toBe('ABCDEF');
});
