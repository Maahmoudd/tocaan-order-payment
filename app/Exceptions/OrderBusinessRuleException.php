<?php

namespace App\Exceptions;

use RuntimeException;

class OrderBusinessRuleException extends RuntimeException
{
    public static function notPending(): self
    {
        return new self('Only pending orders can be updated or deleted.');
    }

    public static function hasPayments(): self
    {
        return new self('Orders with payments cannot be deleted.');
    }
}
