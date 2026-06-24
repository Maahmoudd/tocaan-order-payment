<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

it('processes a payment for a confirmed order using its server-side amount', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '42.50']);

    $this->withHeaders(authHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'stripe',
            'amount' => '0.01',
            'payload' => ['payment_method_id' => 'pm_test'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Successful->value)
        ->assertJsonPath('data.amount', '42.50')
        ->assertJsonMissing(['payment_method_id' => 'pm_test']);
});

it('rejects payments for orders that are not confirmed', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create(['total_amount' => '10.00']);

    $this->withHeaders(authHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'paypal',
            'payload' => ['paypal_order_id' => 'paypal_test'],
        ])
        ->assertConflict();
});

it('validates gateways and gateway-specific payloads', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create(['total_amount' => '10.00']);

    $this->withHeaders(authHeaders($user))
        ->postJson("/api/v1/orders/{$order->id}/payments", [
            'gateway' => 'unsupported',
            'payload' => [],
        ])
        ->assertUnprocessable();

    $this->withHeaders(authHeaders($user))
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

    $this->withHeaders(authHeaders($user))
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
