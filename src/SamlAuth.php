<?php

declare(strict_types=1);

namespace Jdlien\LaravelSaml;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Jdlien\LaravelSaml\Exceptions\AssertException;
use Jdlien\LaravelSaml\Exceptions\MethodNotFoundException;
use Jdlien\LaravelSaml\Exceptions\UnauthenticatedException;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;

class SamlAuth
{
    protected Request $request;

    public function __construct(protected Auth $auth, ?Request $request = null)
    {
        $this->request = $request ?? \request();
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws Error
     */
    public function redirect(
        ?string $returnTo = null,
        array $parameters = [],
        bool $forceAuthn = false,
        bool $isPassive = false,
        bool $setNameIdPolicy = true,
        ?string $nameIdValueReq = null
    ): RedirectResponse {
        $redirectUrl = $this->auth->login(
            returnTo: $returnTo,
            parameters: $parameters,
            forceAuthn: $forceAuthn,
            isPassive: $isPassive,
            stay: true,
            setNameIdPolicy: $setNameIdPolicy,
            nameIdValueReq: $nameIdValueReq
        );

        \session(['saml.authnRequestId' => $this->auth->getLastRequestID()]);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws Error
     */
    public function redirectToLogout(
        ?string $returnTo = null,
        array $parameters = [],
        ?string $nameId = null,
        ?string $sessionIndex = null,
        ?string $nameIdFormat = null,
        ?string $nameIdNameQualifier = null,
        ?string $nameIdSPNameQualifier = null
    ): RedirectResponse {
        $redirectUrl = $this->auth->logout(
            returnTo: $returnTo,
            parameters: $parameters,
            nameId: $nameId,
            sessionIndex: $sessionIndex,
            stay: true,
            nameIdFormat: $nameIdFormat,
            nameIdNameQualifier: $nameIdNameQualifier,
            nameIdSPNameQualifier: $nameIdSPNameQualifier
        );

        \session(['saml.logoutRequestId' => $this->auth->getLastRequestID()]);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * Assertion Consumer Service. Processes the SAML Responses.
     *
     * @throws AssertException
     * @throws UnauthenticatedException
     */
    public function getAuthenticatedUser(): SamlUser
    {
        $this->validateAuthentication();

        return new SamlUser($this->auth);
    }

    /**
     * Process the SAML Logout Response / Logout Request sent by the IdP.
     *
     * Returns a RedirectResponse only when the IdP initiated logout (the SP
     * must redirect back to the IdP with a LogoutResponse). Returns null when
     * the SP initiated logout and processing is complete.
     *
     * @throws AssertException
     */
    public function handleLogoutRequest(?callable $callback = null, bool $retrieveParametersFromServer = false): ?RedirectResponse
    {
        $callback ??= fn () => null;

        try {
            $redirectUrl = $this->auth->processSLO(
                keepLocalSession: false,
                requestId: \session('saml.logoutRequestId'),
                retrieveParametersFromServer: $retrieveParametersFromServer,
                cbDeleteSession: $callback,
                stay: true
            );
        } catch (\Throwable $e) {
            throw new AssertException($this->auth->getErrors(), $this->auth->getLastErrorReason(), $e);
        }

        $errors = $this->auth->getErrors();

        if (! empty($errors)) {
            throw new AssertException($errors, $this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        \session()->forget('saml.logoutRequestId');

        if ($redirectUrl !== '') {
            return new RedirectResponse($redirectUrl);
        }

        return null;
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    /**
     * @param  array<int, mixed>  $arguments
     *
     * @throws MethodNotFoundException
     */
    public function __call(string $name, array $arguments): mixed
    {
        // method_exists() only counts real methods on the class — not magic
        // __call delegates. is_callable() returns true for any name when the
        // target defines __call(), which would silently swallow typos.
        if (method_exists($this->auth, $name)) {
            return \call_user_func_array([$this->auth, $name], $arguments);
        }

        throw new MethodNotFoundException(\sprintf('Method "%s" not found.', $name));
    }

    /**
     * @throws AssertException
     * @throws UnauthenticatedException
     */
    public function validateAuthentication(): void
    {
        try {
            $this->auth->processResponse(\session('saml.authnRequestId'));
        } catch (\Throwable $e) {
            throw new AssertException($this->auth->getErrors(), $this->auth->getLastErrorReason(), $e);
        }

        $errors = $this->auth->getErrors();

        if (! empty($errors)) {
            throw new AssertException($errors, $this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }

        if (! $this->auth->isAuthenticated()) {
            throw new UnauthenticatedException($this->auth->getLastErrorReason(), $this->auth->getLastErrorException());
        }
    }
}
