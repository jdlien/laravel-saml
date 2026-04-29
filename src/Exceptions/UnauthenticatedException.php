<?php

namespace Jdlien\LaravelSaml\Exceptions;

class UnauthenticatedException extends Exception
{
    public function __construct(public ?string $lastErrorReason, ?\Throwable $previous = null)
    {
        parent::__construct($lastErrorReason ?? 'Unauthenticated', 0, $previous);
    }
}
