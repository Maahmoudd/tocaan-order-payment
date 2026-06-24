<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;

it('calculates exact decimal subtotals and order total when creating an order', function () {
    $order = app(OrderService::class)->create(User::factory()->create(), [
        'items' => [
            ['product_name' => 'Precise', 'quantity' => 3, 'unit_price' => '0.10'],
            ['product_name' => 'Second', 'quantity' => 2, 'unit_price' => '1.25'],
        ],
    ]);

    expect($order->total_amount)->toBe('2.80')
        ->and($order->items->pluck('subtotal')->all())->toBe(['0.30', '2.50']);
});

it('replaces items and recalculates the total when updating an order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create(['total_amount' => '99.99']);
    OrderItem::factory()->for($order)->create(['subtotal' => '99.99']);

    $updatedOrder = app(OrderService::class)->update($order, [
        'items' => [
            ['product_name' => 'Replacement', 'quantity' => 5, 'unit_price' => '2.22'],
        ],
    ]);

    expect($updatedOrder->total_amount)->toBe('11.10')
        ->and($updatedOrder->items)->toHaveCount(1)
        ->and($updatedOrder->items->first()->subtotal)->toBe('11.10');
});
