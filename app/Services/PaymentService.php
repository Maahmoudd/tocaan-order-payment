<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentBusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentGatewayManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * @param  array{gateway: string, payload: array<string, mixed>}  $attributes
     */
    public function process(Order $order, array $attributes): Payment
    {
        $gateway = $this->gatewayManager->gateway($attributes['gateway']);

        [$lockedOrder, $payment] = DB::transaction(function () use ($order, $gateway): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw PaymentBusinessRuleException::orderNotConfirmed();
            }

            $payment = $lockedOrder->payments()->create([
                'payment_reference' => (string) Str::uuid(),
                'gateway' => $gateway->getGatewayName(),
                'status' => PaymentStatus::Pending,
                'amount' => $lockedOrder->total_amount,
            ]);

            return [$lockedOrder, $payment];
        });

        $result = $gateway->charge($lockedOrder, $attributes['payload']);

        $payment->update([
            'external_transaction_id' => $result->transactionId,
            'status' => $result->success ? PaymentStatus::Successful : PaymentStatus::Failed,
            'payload' => [
                'response' => $result->rawResponse,
                'message' => $result->message,
            ],
        ]);

        return $payment->refresh();
    }

    /**
     * @param  array{per_page?: int}  $filters
     */
    public function paginateForOrder(Order $order, array $filters): LengthAwarePaginator
    {
        return $order->payments()
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array{gateway?: string, status?: string, per_page?: int}  $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id))
            ->latest();

        if (isset($filters['gateway'])) {
            $payments->where('gateway', $filters['gateway']);
        }

        if (isset($filters['status'])) {
            $payments->where('status', $filters['status']);
        }

        return $payments
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function show(Payment $payment): Payment
    {
        return $payment;
    }
}
