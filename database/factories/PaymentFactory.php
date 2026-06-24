<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(PaymentStatus::cases());

        return [
            'order_id' => Order::factory()->confirmed(),
            'payment_reference' => (string) Str::uuid(),
            'external_transaction_id' => $status === PaymentStatus::Pending
                ? null
                : fake()->unique()->bothify('txn_############'),
            'gateway' => fake()->randomElement(['credit_card', 'paypal', 'stripe']),
            'status' => $status,
            'amount' => fake()->randomFloat(2, 1, 9999.99),
            'payload' => [
                'environment' => 'testing',
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Pending,
            'external_transaction_id' => null,
        ]);
    }

    public function successful(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Successful,
            'external_transaction_id' => fake()->unique()->bothify('txn_############'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'external_transaction_id' => fake()->unique()->bothify('txn_############'),
        ]);
    }
}
