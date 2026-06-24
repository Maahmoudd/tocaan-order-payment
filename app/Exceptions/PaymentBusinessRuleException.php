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
}
