<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\ValueObjects\PaymentResult;

interface PaymentGatewayInterface
{
    public function charge(Order $order, array $payload): PaymentResult;

    public function refund(Payment $payment): PaymentResult;

    public function getGatewayName(): string;
}
