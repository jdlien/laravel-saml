<?php

namespace Jdlien\LaravelSaml\Exceptions;

class AssertException extends Exception
{
    public function __construct(public array $errors, public ?string $lastErrorReason = null, ?\Throwable $previous = null)
    {
        $message = 'SAML Assertion failed: '.($lastErrorReason ?? 'Known');

        parent::__construct($message, 0, $previous);
    }
}
