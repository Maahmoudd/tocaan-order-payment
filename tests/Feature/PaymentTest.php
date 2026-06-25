<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Tests\Fixtures\ThrowingGateway;

it('processes a payment for a confirmed order using its server-side amount', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '42.50']);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'amount' => '0.01',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Successful->value)
        ->assertJsonPath('data.amount', '42.50')
        ->assertJsonMissing(['payment_method_id' => 'pm_test'])
        ->assertJsonMissingPath('data.payload');
});

it('rejects payments for orders that are not confirmed', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create(['total_amount' => '10.00']);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'paypal',
            'payload' => ['paypal_order_id' => 'paypal_test'],
        ])
        ->assertConflict();
});

it('validates gateways and gateway-specific payloads', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '10.00']);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'unsupported',
            'payload' => [],
        ])
        ->assertUnprocessable();

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'credit_card',
            'payload' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payload.card_token');
});

it('persists failed gateway outcomes', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '25.00']);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'credit_card',
            'payload' => [
                'card_token' => 'tok_test',
                'simulate_failure' => true,
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Failed->value);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'status' => PaymentStatus::Failed->value,
    ]);
});

it('requires an idempotency key when processing a payment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '10.00']);

    $this->withHeaders(authHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('idempotency_key');
});

it('returns the original payment when an idempotency key is replayed', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '15.00']);
    $headers = paymentHeaders($user, 'same-payment-request');
    $payload = [
        'gateway' => 'stripe',
        'payload' => ['payment_method_id' => 'pm_test'],
    ];

    $firstPaymentId = $this->withHeaders($headers)
        ->postJson("/api/v1/orders/{$order->id}/payments", $payload)
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/orders/{$order->id}/payments", $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $firstPaymentId);

    expect($order->payments()->count())->toBe(1);
});

it('prevents a second successful payment for the same order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '15.00']);
    $payload = [
        'gateway' => 'stripe',
        'payload' => ['payment_method_id' => 'pm_test'],
    ];

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", $payload)
        ->assertCreated();

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", $payload)
        ->assertConflict();
});

it('allows a new attempt after a definitive failed payment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '15.00']);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'payload' => [
                'payment_method_id' => 'pm_test',
                'simulate_failure' => true,
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Failed->value);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Successful->value);
});

it('marks an indeterminate provider error as unknown and blocks another attempt', function () {
    config()->set('payment.gateways.throwing', ThrowingGateway::class);

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '15.00']);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'throwing',
            'payload' => ['simulate_failure' => false],
        ])
        ->assertStatus(502);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'status' => PaymentStatus::Unknown->value,
    ]);

    $this->withHeaders(paymentHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertConflict();
});

it('rejects reuse of an idempotency key for another payment request', function () {
    $user = User::factory()->create();
    $firstOrder = Order::factory()->for($user)->confirmed()->create(['total_amount' => '15.00']);
    $secondOrder = Order::factory()->for($user)->confirmed()->create(['total_amount' => '20.00']);
    $headers = paymentHeaders($user, 'shared-key');

    $this->withHeaders($headers)
        ->postJson("/api/v1/orders/{$firstOrder->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson("/api/v1/orders/{$secondOrder->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertConflict();
});

it('rejects reuse of an idempotency key with different request parameters', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '15.00']);
    $headers = paymentHeaders($user, 'parameter-sensitive-key');

    $this->withHeaders($headers)
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_first'],
        ])
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'payload' => ['payment_method_id' => 'pm_changed'],
        ])
        ->assertConflict();
});

it('scopes payment listings and payment details to the order owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner)->confirmed()->create();
    $payment = Payment::factory()->for($order)->successful()->create();

    $this->withHeaders(authHeaders($owner))
        ->getJson('/api/v1/payments?gateway='.$payment->gateway.'&status=successful')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withHeaders(authHeaders($otherUser))
        ->getJson('/api/v1/payments')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->withHeaders(authHeaders($otherUser))
        ->getJson("/api/v1/payments/{$payment->id}")
        ->assertForbidden();
});
