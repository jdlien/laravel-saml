<?php

declare(strict_types=1);

namespace Jdlien\LaravelSaml;

use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use OneLogin\Saml2\Auth;

/**
 * @extends Fluent<string, mixed>
 *
 * @method string getNameIdFormat()
 * @method string getNameIdNameQualifier()
 * @method string getNameIdSPNameQualifier()
 * @method array<string, mixed>|null getAttributeWithFriendlyName(string $friendlyName)
 * @method array<string, mixed>|null getAttributesWithFriendlyName()
 */
class SamlUser extends Fluent
{
    protected Request $request;

    public function __construct(protected Auth $auth, ?Request $request = null)
    {
        parent::__construct();
        $this->request = $request ?? \request();
        $this->parseAttributes($this->auth->getAttributes());
    }

    public function getUserId(): string
    {
        return $this->auth->getNameId();
    }

    /**
     * Returns a safe redirect target derived from the SAML RelayState.
     *
     * RelayState is attacker-controllable. To prevent open redirects, this
     * method only returns:
     *   - relative paths (e.g. "/dashboard")
     *   - absolute URLs whose host matches the application host
     *
     * Cross-origin or malformed values return null. Use getRawRelayState()
     * if you need the unvalidated value.
     */
    public function getIntendedUrl(): ?string
    {
        $relayState = $this->getRawRelayState();

        if ($relayState === null || $relayState === '') {
            return null;
        }

        // Reject self-redirect loops (the IdP may pass back our own ACS URL).
        if ($relayState === \url()->full()) {
            return null;
        }

        // Relative paths are safe — they can only resolve within the app.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $relayState)) {
            return $relayState;
        }

        // Absolute URLs: only allow same-host.
        $host = parse_url($relayState, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return strcasecmp($host, $this->request->getHost()) === 0
            ? $relayState
            : null;
    }

    /**
     * The raw, unvalidated RelayState value from the request.
     *
     * Prefer getIntendedUrl() unless you have an explicit reason to use this.
     */
    public function getRawRelayState(): ?string
    {
        $value = $this->request->input('RelayState');

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSamlAttribute(string $attribute): ?array
    {
        return $this->auth->getAttribute($attribute);
    }

    /**
     * @param  iterable<string, mixed>  $attributes
     */
    public function parseAttributes(iterable $attributes = []): static
    {
        foreach ($attributes as $propertyName => $samlAttribute) {
            $this->$propertyName = $samlAttribute;
        }

        return $this;
    }

    public function getSessionIndex(): ?string
    {
        return $this->auth->getSessionIndex();
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        return \call_user_func_array([$this->auth, $method], $parameters);
    }
}
