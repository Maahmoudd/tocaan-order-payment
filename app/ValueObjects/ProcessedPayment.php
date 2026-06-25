<?php

namespace App\ValueObjects;

use App\Models\Payment;

readonly class ProcessedPayment
{
    public function __construct(
        public Payment $payment,
        public bool $replayed,
    ) {}
}
