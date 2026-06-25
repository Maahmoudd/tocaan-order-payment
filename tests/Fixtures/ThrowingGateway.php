<?php

namespace Tests\Fixtures;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\ValueObjects\PaymentResult;
use RuntimeException;

class ThrowingGateway implements PaymentGatewayInterface
{
    public function charge(Order $order, array $payload): PaymentResult
    {
        throw new RuntimeException('Simulated provider timeout.');
    }

    public function refund(Payment $payment): PaymentResult
    {
        throw new RuntimeException('Not implemented by this test gateway.');
    }

    public function getGatewayName(): string
    {
        return 'throwing';
    }
}
