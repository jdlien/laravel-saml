<?php

declare(strict_types=1);

use Jdlien\LaravelSaml\SamlUser;
use OneLogin\Saml2\Auth;

function makeAuthMock(array $attributes = []): Auth
{
    $auth = Mockery::mock(Auth::class);
    $auth->expects()->getAttributes()->andReturns($attributes);

    return $auth;
}

it('exposes the NameID as the user id', function () {
    $auth = makeAuthMock();
    $auth->expects()->getNameId()->andReturns('user@domain.com');

    expect((new SamlUser($auth))->getUserId())->toBe('user@domain.com');
});

it('returns attributes with their friendly names', function () {
    $auth = makeAuthMock();
    $auth->expects()->getAttributesWithFriendlyName()->andReturns(['email' => 'user@domain.com']);

    expect((new SamlUser($auth))->getAttributesWithFriendlyName())
        ->toBe(['email' => 'user@domain.com']);
});

it('looks up a single SAML attribute by name', function () {
    $auth = makeAuthMock();
    $auth->expects()->getAttribute('email')->andReturns(['email' => 'user@domain.com']);

    expect((new SamlUser($auth))->getSamlAttribute('email'))
        ->toBe(['email' => 'user@domain.com']);
});

it('exposes the session index from the assertion', function () {
    $auth = makeAuthMock();
    $auth->expects()->getSessionIndex()->andReturns('mock-session-index');

    expect((new SamlUser($auth))->getSessionIndex())->toBe('mock-session-index');
});

it('returns the underlying OneLogin Auth instance', function () {
    $auth = makeAuthMock();

    expect((new SamlUser($auth))->getAuth())->toBe($auth);
});

describe('parseAttributes', function () {
    it('uses the values supplied in the parameter array directly', function () {
        $auth = Mockery::mock(Auth::class);
        // First call (constructor) — returns empty attributes so the constructor's
        // implicit parseAttributes() doesn't fight with our subsequent call.
        $auth->expects()->getAttributes()->andReturns([]);

        $user = new SamlUser($auth);
        // Critical: this must NOT call back into $auth->getAttribute() on each key.
        // Pre-fix, the body re-fetched via getAttribute(), making the param a no-op.
        $user->parseAttributes(['email' => 'a@b.com', 'name' => 'Alice']);

        expect($user->email)->toBe('a@b.com')
            ->and($user->name)->toBe('Alice');
    });
});

describe('getIntendedUrl', function () {
    beforeEach(function () {
        $this->auth = makeAuthMock();
    });

    it('returns null when RelayState is absent', function () {
        \request()->merge([]);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBeNull();
    });

    it('returns relative paths as-is (always safe)', function () {
        \request()->merge(['RelayState' => '/dashboard']);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBe('/dashboard');
    });

    it('returns same-host absolute URLs', function () {
        $sameHost = 'http://'.\request()->getHost().'/path';
        \request()->merge(['RelayState' => $sameHost]);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBe($sameHost);
    });

    it('rejects cross-origin absolute URLs (open-redirect protection)', function () {
        \request()->merge(['RelayState' => 'http://attacker.example.com/phish']);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBeNull();
    });

    it('rejects malformed URLs', function () {
        \request()->merge(['RelayState' => 'http://']);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBeNull();
    });

    it('rejects protocol-relative URLs (//attacker.example.com/path)', function () {
        \request()->merge(['RelayState' => '//attacker.example.com/phish']);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBeNull();
    });

    it('rejects javascript: and other non-http schemes via the host check', function () {
        \request()->merge(['RelayState' => 'javascript:alert(1)']);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBeNull();
    });

    it('rejects RelayState that loops back to the current URL', function () {
        $current = \url()->full();
        \request()->merge(['RelayState' => $current]);

        expect((new SamlUser($this->auth))->getIntendedUrl())->toBeNull();
    });

    it('exposes raw RelayState through getRawRelayState() escape hatch', function () {
        \request()->merge(['RelayState' => 'http://attacker.example.com/phish']);

        $user = new SamlUser($this->auth);

        expect($user->getRawRelayState())->toBe('http://attacker.example.com/phish')
            ->and($user->getIntendedUrl())->toBeNull();
    });

    it('returns null when RelayState is not a string', function () {
        \request()->merge(['RelayState' => ['array', 'value']]);

        $user = new SamlUser($this->auth);

        expect($user->getRawRelayState())->toBeNull()
            ->and($user->getIntendedUrl())->toBeNull();
    });
});

describe('__call passthrough', function () {
    it('forwards unknown method calls to the underlying Auth instance', function () {
        $auth = makeAuthMock();
        $auth->expects()->getNameIdFormat()->andReturns('urn:oasis:names:tc:SAML:2.0:nameid-format:emailAddress');

        $user = new SamlUser($auth);

        expect($user->getNameIdFormat())
            ->toBe('urn:oasis:names:tc:SAML:2.0:nameid-format:emailAddress');
    });
});
