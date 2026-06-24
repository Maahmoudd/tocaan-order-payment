<?php

namespace App\ValueObjects;

readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly string $message,
        public readonly array $rawResponse = [],
    ) {}
}
