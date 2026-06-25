<?php

namespace App\Exceptions;

use RuntimeException;

class AuthBusinessRuleException extends RuntimeException
{
    public static function emailAlreadyRegistered(): self
    {
        return new self('An account with this email address already exists.');
    }
}
