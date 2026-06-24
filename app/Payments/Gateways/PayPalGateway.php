<?php

namespace App\Payments\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\ValueObjects\PaymentResult;
use Illuminate\Support\Str;

class PayPalGateway implements PaymentGatewayInterface
{
    public function charge(Order $order, array $payload): PaymentResult
    {
        $transactionId = 'paypal_'.Str::lower((string) Str::ulid());
        $success = ! ($payload['simulate_failure'] ?? false);

        return new PaymentResult(
            success: $success,
            transactionId: $transactionId,
            message: $success ? 'PayPal payment processed.' : 'PayPal payment failed.',
            rawResponse: [
                'gateway' => $this->getGatewayName(),
                'transaction_id' => $transactionId,
                'status' => $success ? 'completed' : 'failed',
            ],
        );
    }

    public function refund(Payment $payment): PaymentResult
    {
        $transactionId = 'paypal_refund_'.Str::lower((string) Str::ulid());

        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            message: 'PayPal payment refunded.',
            rawResponse: [
                'gateway' => $this->getGatewayName(),
                'original_transaction_id' => $payment->external_transaction_id,
                'refund_transaction_id' => $transactionId,
            ],
        );
    }

    public function getGatewayName(): string
    {
        return 'paypal';
    }
}
