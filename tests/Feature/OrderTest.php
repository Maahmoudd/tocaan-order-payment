<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

it('requires authentication for order endpoints', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('creates an order with server-calculated subtotals and total', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeaders($user))->postJson('/api/v1/orders', [
        'total_amount' => '0.01',
        'notes' => 'Test order',
        'items' => [
            ['product_name' => 'First', 'quantity' => 2, 'unit_price' => '10.25'],
            ['product_name' => 'Second', 'quantity' => 3, 'unit_price' => '1.10'],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '23.80')
        ->assertJsonPath('data.items.0.subtotal', '20.50')
        ->assertJsonPath('data.items.1.subtotal', '3.30');

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'total_amount' => '23.80',
    ]);
});

it('returns only the authenticated users filtered orders', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Order::factory()->for($user)->pending()->count(2)->create();
    Order::factory()->for($user)->confirmed()->create();
    Order::factory()->for($otherUser)->pending()->create();

    $this->withHeaders(authHeaders($user))
        ->getJson('/api/v1/orders?status=pending&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('prevents another user from viewing or updating an order', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner)->pending()->create();

    $this->withHeaders(authHeaders($otherUser))
        ->getJson("/api/v1/orders/{$order->id}")
        ->assertForbidden();

    $this->withHeaders(authHeaders($otherUser))
        ->putJson("/api/v1/orders/{$order->id}", ['notes' => 'Unauthorized'])
        ->assertForbidden();
});

it('updates items and recalculates the order total', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    $this->withHeaders(authHeaders($user))
        ->putJson("/api/v1/orders/{$order->id}", [
            'items' => [
                ['product_name' => 'Replacement', 'quantity' => 4, 'unit_price' => '2.50'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.total_amount', '10.00')
        ->assertJsonCount(1, 'data.items');
});

it('allows mutations only while an order is pending', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();

    $this->withHeaders(authHeaders($user))
        ->putJson("/api/v1/orders/{$order->id}", ['notes' => 'Blocked'])
        ->assertConflict();

    $this->withHeaders(authHeaders($user))
        ->deleteJson("/api/v1/orders/{$order->id}")
        ->assertConflict();
});

it('does not delete a pending order that has payments', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create(['total_amount' => '10.00']);
    Payment::factory()->for($order)->pending()->create(['amount' => '10.00']);

    $this->withHeaders(authHeaders($user))
        ->deleteJson("/api/v1/orders/{$order->id}")
        ->assertConflict();

    expect($order->fresh()?->status)->toBe(OrderStatus::Pending);
});

it('soft deletes a pending order without payments', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    $this->withHeaders(authHeaders($user))
        ->deleteJson("/api/v1/orders/{$order->id}")
        ->assertOk();

    $this->assertSoftDeleted($order);
});
