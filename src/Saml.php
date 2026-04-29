<?php

declare(strict_types=1);

namespace Jdlien\LaravelSaml;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Jdlien\LaravelSaml\Exceptions\InvalidConfigException;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Settings;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @method static void validateAuthentication()
 * @method static \Illuminate\Http\RedirectResponse redirect()
 * @method static \Illuminate\Http\RedirectResponse redirectToLogout()
 * @method static \Jdlien\LaravelSaml\SamlUser getAuthenticatedUser()
 * @method static \Illuminate\Http\RedirectResponse|null handleLogoutRequest(?callable $callback = null, bool $retrieveParametersFromServer = false)
 * @method static \Illuminate\Http\Response getMetadataXML()
 * @method static \Symfony\Component\HttpFoundation\StreamedResponse getMetadataXMLAsStreamResponse()
 */
class Saml
{
    public const DEFAULT_IDP_NAME = 'default';

    /**
     * @var array<string, SamlAuth>
     */
    protected static array $resolved = [];

    protected static ?\Closure $idpConfigResolver = null;

    /**
     * @param  array<string, mixed>|null  $settings
     *
     * @throws Error
     * @throws InvalidConfigException
     */
    public static function idp(?string $idpName = self::DEFAULT_IDP_NAME, ?array $settings = null): SamlAuth
    {
        $idpName ??= self::DEFAULT_IDP_NAME;

        if (! isset(self::$resolved[$idpName])) {
            $idpConfig = $settings ?? (self::$idpConfigResolver ? \call_user_func(self::$idpConfigResolver, $idpName) : null);

            if (! \is_array($idpConfig) || empty($idpConfig)) {
                throw new InvalidConfigException('Cannot resolve idp config from resolver.');
            }

            $resolved = self::normalizeConfig(array_merge(\config('saml', []), ['idp' => $idpConfig]));

            self::$resolved[$idpName] = new SamlAuth(new Auth($resolved));
        }

        return self::$resolved[$idpName];
    }

    public static function configureIdpUsing(\Closure $closure): void
    {
        self::$idpConfigResolver = $closure;

        // Drop cached SamlAuth instances so subsequent idp() calls re-resolve
        // through the new closure. Important for tests, multi-tenant flows,
        // and long-running workers (Octane, queue, etc.).
        self::$resolved = [];
    }

    /**
     * Clear all cached SamlAuth instances.
     *
     * Call this when IdP configuration changes mid-process or between
     * Octane/queue requests to avoid stale bindings leaking across boundaries.
     */
    public static function flushResolvedIdps(): void
    {
        self::$resolved = [];
    }

    /**
     * @param  array<int, mixed>  $arguments
     *
     * @throws InvalidConfigException
     * @throws Error
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return \call_user_func_array([self::idp(self::DEFAULT_IDP_NAME), $name], $arguments);
    }

    /**
     * @throws InvalidConfigException
     */
    public static function getMetadataXML(): Response
    {
        try {
            // Normalize first: turn file-path cert/key entries into the inlined
            // base64 strings OneLogin expects. Without this, Settings embeds
            // the literal paths into the metadata XML, which then fails XSD
            // validation downstream.
            $normalized = self::normalizeConfig((array) \config('saml', []));

            $settings = new Settings($normalized, true);
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);

            if (empty($errors)) {
                return new Response($metadata, 200, ['Content-Type' => 'text/xml']);
            }

            // @codeCoverageIgnoreStart
            // Defense in depth: with valid Settings construction, OneLogin's
            // generated metadata is always XSD-valid, so this branch is only
            // reachable if the SAML2 metadata XSD itself fails to load (env-
            // specific libxml issue).
            throw new InvalidConfigException(
                sprintf('Invalid SP metadata: %s', implode(', ', $errors)),
                Error::METADATA_SP_INVALID,
            );
            // @codeCoverageIgnoreEnd
        } catch (\Throwable $e) {
            throw new InvalidConfigException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function getMetadataXMLAsStreamResponse(?string $filename = null): StreamedResponse
    {
        $filename ??= Str::slug((string) \config('app.name')).'-metadata.xml';

        return \response()->streamDownload(function (): void {
            echo static::getMetadataXML()->getContent();
        }, $filename);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidConfigException
     */
    public static function normalizeConfig(array $config): array
    {
        if (empty($config['sp']['entityId'])) {
            throw new InvalidConfigException('Please configure the "saml.sp.entityId".');
        }

        if (empty($config['sp']['assertionConsumerService']['url'])) {
            throw new InvalidConfigException('Please configure the "saml.sp.assertionConsumerService.url".');
        }

        if (! empty($config['sp']['singleLogoutService']) && empty($config['sp']['singleLogoutService']['url'])) {
            throw new InvalidConfigException('Please configure the "saml.sp.singleLogoutService.url".');
        }

        if (self::looksLikePath($config['sp']['privateKey'] ?? null) && file_exists($config['sp']['privateKey'])) {
            $config['sp']['privateKey'] = Utils::loadKeyFromFile($config['sp']['privateKey']);
        }

        if (self::looksLikePath($config['sp']['x509cert'] ?? null) && file_exists($config['sp']['x509cert'])) {
            $config['sp']['x509cert'] = Utils::loadCertFromFile($config['sp']['x509cert']);
        }

        if (self::looksLikePath($config['idp']['x509cert'] ?? null) && file_exists($config['idp']['x509cert'])) {
            $config['idp']['x509cert'] = Utils::loadCertFromFile($config['idp']['x509cert']);
        }

        return $config;
    }

    /**
     * Distinguish a filesystem path from an inlined PEM string.
     *
     * Guards file_exists() against PHP 8.3+ ValueError on long inputs and
     * against the obvious case of an inlined PEM (which contains newlines).
     */
    private static function looksLikePath(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && strlen($value) <= 4096
            && ! str_contains($value, "\n");
    }
}
