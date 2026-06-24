<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        User::factory(5)->create()->each(function (User $user): void {
            Order::factory(3)
                ->for($user)
                ->create()
                ->each(function (Order $order): void {
                    OrderItem::factory(fake()->numberBetween(1, 4))
                        ->for($order)
                        ->create();

                    $totalAmount = $order->items()->sum('subtotal');
                    $order->update(['total_amount' => $totalAmount]);

                    if ($order->status === OrderStatus::Confirmed) {
                        Payment::factory()
                            ->for($order)
                            ->create(['amount' => $totalAmount]);
                    }
                });
        });
    }
}
