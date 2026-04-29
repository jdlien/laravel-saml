<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Jdlien\LaravelSaml\Exceptions\AssertException;
use Jdlien\LaravelSaml\Exceptions\MethodNotFoundException;
use Jdlien\LaravelSaml\Exceptions\UnauthenticatedException;
use Jdlien\LaravelSaml\SamlAuth;
use Jdlien\LaravelSaml\SamlUser;
use OneLogin\Saml2\Auth;

describe('redirect', function () {
    it('asks OneLogin for the login URL and returns a Redirect to it', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('login')->once()->andReturn('https://idp.example.com/sso?auth=abc');
        $auth->shouldReceive('getLastRequestID')->once()->andReturn('request-id-123');

        $response = (new SamlAuth($auth))->redirect();

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe('https://idp.example.com/sso?auth=abc')
            ->and(\session('saml.authnRequestId'))->toBe('request-id-123');
    });
});

describe('redirectToLogout', function () {
    it('asks OneLogin for the logout URL and returns a Redirect to it', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('logout')->once()->andReturn('https://idp.example.com/slo?req=abc');
        $auth->shouldReceive('getLastRequestID')->once()->andReturn('logout-id-456');

        $response = (new SamlAuth($auth))->redirectToLogout();

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe('https://idp.example.com/slo?req=abc')
            ->and(\session('saml.logoutRequestId'))->toBe('logout-id-456');
    });
});

describe('validateAuthentication', function () {
    it('passes when OneLogin reports no errors and the assertion is authenticated', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse')->once();
        $auth->shouldReceive('getErrors')->andReturn([]);
        $auth->shouldReceive('isAuthenticated')->once()->andReturn(true);

        (new SamlAuth($auth))->validateAuthentication();
    });

    it('throws AssertException when OneLogin reports errors after processing', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse')->once();
        $auth->shouldReceive('getErrors')->andReturn(['invalid_response']);
        $auth->shouldReceive('getLastErrorReason')->andReturn('Invalid issuer');
        $auth->shouldReceive('getLastErrorException')->andReturnNull();

        (new SamlAuth($auth))->validateAuthentication();
    })->throws(AssertException::class, 'Invalid issuer');

    it('throws AssertException when processResponse itself throws', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse')->andThrow(new RuntimeException('XML parse failure'));
        $auth->shouldReceive('getErrors')->andReturn(['xml_error']);
        $auth->shouldReceive('getLastErrorReason')->andReturn('Bad XML');

        (new SamlAuth($auth))->validateAuthentication();
    })->throws(AssertException::class);

    it('throws UnauthenticatedException when no error but not authenticated', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse')->once();
        $auth->shouldReceive('getErrors')->andReturn([]);
        $auth->shouldReceive('isAuthenticated')->once()->andReturn(false);
        $auth->shouldReceive('getLastErrorReason')->andReturnNull();
        $auth->shouldReceive('getLastErrorException')->andReturnNull();

        (new SamlAuth($auth))->validateAuthentication();
    })->throws(UnauthenticatedException::class);
});

describe('getAuthenticatedUser', function () {
    it('returns a SamlUser after a successful validation', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse')->once();
        $auth->shouldReceive('getErrors')->andReturn([]);
        $auth->shouldReceive('isAuthenticated')->once()->andReturn(true);
        $auth->shouldReceive('getAttributes')->andReturn([]);

        $user = (new SamlAuth($auth))->getAuthenticatedUser();

        expect($user)->toBeInstanceOf(SamlUser::class);
    });
});

describe('handleLogoutRequest', function () {
    it('returns null when SP-initiated logout completes (no redirect URL)', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processSLO')->once()->andReturn('');
        $auth->shouldReceive('getErrors')->andReturn([]);

        $result = (new SamlAuth($auth))->handleLogoutRequest();

        expect($result)->toBeNull();
    });

    it('returns null when processSLO returns null (SP-initiated LogoutResponse path)', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processSLO')->once()->andReturnNull();
        $auth->shouldReceive('getErrors')->andReturn([]);

        $result = (new SamlAuth($auth))->handleLogoutRequest();

        expect($result)->toBeNull();
    });

    it('returns a RedirectResponse when IdP-initiated logout requires a redirect back', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processSLO')->once()->andReturn('https://idp.example.com/slo-response?id=xyz');
        $auth->shouldReceive('getErrors')->andReturn([]);

        $result = (new SamlAuth($auth))->handleLogoutRequest();

        expect($result)->toBeInstanceOf(RedirectResponse::class)
            ->and($result->getTargetUrl())->toBe('https://idp.example.com/slo-response?id=xyz');
    });

    it('throws AssertException when processSLO itself throws', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processSLO')->andThrow(new RuntimeException('SLO parse failure'));
        $auth->shouldReceive('getErrors')->andReturn(['slo_error']);
        $auth->shouldReceive('getLastErrorReason')->andReturn('Bad SLO');

        (new SamlAuth($auth))->handleLogoutRequest();
    })->throws(AssertException::class);

    it('throws AssertException when processSLO succeeds but errors are reported', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processSLO')->once()->andReturn('');
        $auth->shouldReceive('getErrors')->andReturn(['logout_error_after']);
        $auth->shouldReceive('getLastErrorReason')->andReturn('Logout post-validation failed');
        $auth->shouldReceive('getLastErrorException')->andReturnNull();

        (new SamlAuth($auth))->handleLogoutRequest();
    })->throws(AssertException::class);
});

describe('__call', function () {
    it('forwards calls to OneLogin Auth methods that exist', function () {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('getLastRequestID')->once()->andReturn('passthrough-id');

        $samlAuth = new SamlAuth($auth);

        expect($samlAuth->getLastRequestID())->toBe('passthrough-id');
    });

    it('throws MethodNotFoundException for methods OneLogin Auth does not expose', function () {
        $auth = Mockery::mock(Auth::class);

        $samlAuth = new SamlAuth($auth);
        $samlAuth->thisMethodDoesNotExistAnywhere();
    })->throws(MethodNotFoundException::class, 'thisMethodDoesNotExistAnywhere');
});

describe('getAuth', function () {
    it('returns the underlying OneLogin Auth instance', function () {
        $auth = Mockery::mock(Auth::class);

        expect((new SamlAuth($auth))->getAuth())->toBe($auth);
    });
});
