<?php

declare(strict_types=1);

namespace Jdlien\LaravelSaml\Exceptions;

class AssertException extends Exception
{
    /**
     * @param  array<int|string, mixed>  $errors
     */
    public function __construct(
        public readonly array $errors,
        public readonly ?string $lastErrorReason = null,
        ?\Throwable $previous = null,
    ) {
        $message = 'SAML Assertion failed: '.($lastErrorReason ?? 'Known');

        parent::__construct($message, 0, $previous);
    }
}
