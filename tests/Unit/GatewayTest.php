<?php

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\Gateways\StripeGateway;
use App\ValueObjects\PaymentResult;

dataset('gateways', [
    'credit card' => [CreditCardGateway::class, 'credit_card'],
    'paypal' => [PayPalGateway::class, 'paypal'],
    'stripe' => [StripeGateway::class, 'stripe'],
]);

it('charges successfully through each gateway', function (string $gatewayClass, string $gatewayName) {
    $gateway = app($gatewayClass);
    $result = $gateway->charge(Order::factory()->make(), []);

    expect($gateway)->toBeInstanceOf(PaymentGatewayInterface::class)
        ->and($gateway->getGatewayName())->toBe($gatewayName)
        ->and($result)->toBeInstanceOf(PaymentResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->transactionId)->not->toBeEmpty();
})->with('gateways');

it('returns a failed result when failure is simulated', function (string $gatewayClass) {
    $result = app($gatewayClass)->charge(
        Order::factory()->make(),
        ['simulate_failure' => true],
    );

    expect($result->success)->toBeFalse()
        ->and($result->transactionId)->not->toBeEmpty();
})->with('gateways');

it('refunds through each gateway', function (string $gatewayClass) {
    $result = app($gatewayClass)->refund(
        Payment::factory()->make(['external_transaction_id' => 'txn_original']),
    );

    expect($result->success)->toBeTrue()
        ->and($result->transactionId)->toContain('refund');
})->with('gateways');
