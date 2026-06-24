<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\OrderBusinessRuleException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * @param  array{status?: string, per_page?: int}  $filters
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $orders = $user->orders()
            ->withCount('payments')
            ->latest();

        if (isset($filters['status'])) {
            $orders->where('status', $filters['status']);
        }

        return $orders
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     notes?: string|null,
     *     items: array<int, array{product_name: string, quantity: int, unit_price: string}>
     * }  $attributes
     */
    public function create(User $user, array $attributes): Order
    {
        return DB::transaction(function () use ($user, $attributes): Order {
            $order = $user->orders()->create([
                'status' => OrderStatus::Pending,
                'total_amount' => '0.00',
                'notes' => $attributes['notes'] ?? null,
            ]);

            $totalAmount = $this->replaceItems($order, $attributes['items']);
            $order->update(['total_amount' => $totalAmount]);

            return $order->load('items')->loadCount('payments');
        });
    }

    public function show(Order $order): Order
    {
        return $order->load('items')->loadCount('payments');
    }

    /**
     * @param  array{
     *     status?: string,
     *     notes?: string|null,
     *     items?: array<int, array{product_name: string, quantity: int, unit_price: string}>
     * }  $attributes
     */
    public function update(Order $order, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $attributes): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->ensurePending($lockedOrder);

            $orderAttributes = Arr::only($attributes, ['status', 'notes']);

            if (isset($attributes['items'])) {
                $orderAttributes['total_amount'] = $this->replaceItems($lockedOrder, $attributes['items']);
            }

            $lockedOrder->update($orderAttributes);

            return $lockedOrder->load('items')->loadCount('payments');
        });
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->ensurePending($lockedOrder);

            if ($lockedOrder->payments()->exists()) {
                throw OrderBusinessRuleException::hasPayments();
            }

            $lockedOrder->delete();
        });
    }

    /**
     * @param  array<int, array{product_name: string, quantity: int, unit_price: string}>  $items
     */
    private function replaceItems(Order $order, array $items): string
    {
        $order->items()->delete();
        $totalCents = 0;

        foreach ($items as $item) {
            $subtotalCents = $this->moneyToCents((string) $item['unit_price']) * $item['quantity'];
            $subtotal = $this->centsToMoney($subtotalCents);

            $order->items()->create([
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $subtotal,
            ]);

            $totalCents += $subtotalCents;
        }

        return $this->centsToMoney($totalCents);
    }

    private function ensurePending(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw OrderBusinessRuleException::notPending();
        }
    }

    private function moneyToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
