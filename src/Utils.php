<?php

declare(strict_types=1);

namespace Jdlien\LaravelSaml;

use Illuminate\Support\Str;
use Jdlien\LaravelSaml\Exceptions\InvalidConfigException;

class Utils
{
    /**
     * @throws InvalidConfigException
     */
    public static function loadKeyFromFile(string $path): string
    {
        self::assertReadable($path, 'private key');

        $privateKey = @openssl_get_privatekey(Str::start($path, 'file://'));

        if (empty($privateKey)) {
            throw new InvalidConfigException("Could not parse private key from '{$path}'.");
        }

        openssl_pkey_export($privateKey, $contents);

        return self::extractOpensslString((string) $contents, 'PRIVATE KEY');
    }

    /**
     * @throws InvalidConfigException
     */
    public static function loadCertFromFile(string $path): string
    {
        self::assertReadable($path, 'X509 certificate');

        $contents = file_get_contents($path);
        if ($contents === false) {
            // @codeCoverageIgnoreStart
            // Defense in depth: assertReadable() above already verified the
            // path is readable, so this branch only fires on a genuinely
            // hostile filesystem (e.g. file removed mid-read).
            throw new InvalidConfigException("Could not read X509 certificate from '{$path}'.");
            // @codeCoverageIgnoreEnd
        }

        $certificate = @openssl_x509_read($contents);

        if ($certificate === false) {
            throw new InvalidConfigException("Could not parse X509 certificate from '{$path}'.");
        }

        openssl_x509_export($certificate, $exported);

        return self::extractOpensslString((string) $exported, 'CERTIFICATE');
    }

    public static function extractOpensslString(string $contents, string $delimiter): string
    {
        $contents = str_replace(["\r", "\n"], '', $contents);

        $regex = '/-{5}BEGIN(?:\s|\w)+'.preg_quote($delimiter, '/').'-{5}\s*(.+?)\s*-{5}END(?:\s|\w)+'.preg_quote($delimiter, '/').'-{5}/m';

        preg_match($regex, $contents, $matches);

        return empty($matches[1]) ? '' : $matches[1];
    }

    /**
     * @throws InvalidConfigException
     */
    private static function assertReadable(string $path, string $description): void
    {
        if (! is_readable($path)) {
            throw new InvalidConfigException(
                sprintf("The %s file '%s' does not exist or is not readable.", $description, $path)
            );
        }
    }
}
