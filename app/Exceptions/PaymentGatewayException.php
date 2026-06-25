<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    public static function outcomeUnknown(): self
    {
        return new self('The payment provider did not return a definitive result. The payment must be reconciled before retrying.');
    }
}
