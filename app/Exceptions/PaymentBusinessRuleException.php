<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentBusinessRuleException extends RuntimeException
{
    public static function orderNotConfirmed(): self
    {
        return new self('Payments can only be processed for confirmed orders.');
    }

    public static function unsupportedGateway(string $gateway): self
    {
        return new self("The payment gateway [{$gateway}] is not supported.");
    }

    public static function invalidGateway(string $gateway): self
    {
        return new self("The configured payment gateway [{$gateway}] is invalid.");
    }

    public static function alreadyPaid(): self
    {
        return new self('This order already has a successful payment.');
    }

    public static function unresolvedAttempt(): self
    {
        return new self('This order already has an unresolved payment attempt.');
    }

    public static function idempotencyKeyConflict(): self
    {
        return new self('The idempotency key has already been used for another payment request.');
    }
}
