<?php

declare(strict_types=1);

use Jdlien\LaravelSaml\Exceptions\AssertException;
use Jdlien\LaravelSaml\Exceptions\UnauthenticatedException;

covers(AssertException::class);
covers(UnauthenticatedException::class);

describe('AssertException', function () {
    it('builds a message from the given reason and exposes errors + reason as readonly properties', function () {
        $errors = ['invalid_response', 'cert_mismatch'];
        $exception = new AssertException($errors, 'cert_mismatch');

        expect($exception->getMessage())->toBe('SAML Assertion failed: cert_mismatch')
            ->and($exception->errors)->toBe($errors)
            ->and($exception->lastErrorReason)->toBe('cert_mismatch');
    });

    it('falls back to a "Known" reason when none is provided', function () {
        $exception = new AssertException([]);

        expect($exception->getMessage())->toBe('SAML Assertion failed: Known')
            ->and($exception->lastErrorReason)->toBeNull();
    });
});

describe('UnauthenticatedException', function () {
    it('uses the given reason as the message and exposes it as a readonly property', function () {
        $exception = new UnauthenticatedException('session_expired');

        expect($exception->getMessage())->toBe('session_expired')
            ->and($exception->lastErrorReason)->toBe('session_expired');
    });

    it('falls back to "Unauthenticated" when reason is null', function () {
        $exception = new UnauthenticatedException(null);

        expect($exception->getMessage())->toBe('Unauthenticated')
            ->and($exception->lastErrorReason)->toBeNull();
    });
});
