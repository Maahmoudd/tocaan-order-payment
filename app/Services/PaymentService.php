<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentBusinessRuleException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentGatewayManager;
use App\ValueObjects\ProcessedPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * @param  array{gateway: string, idempotency_key: string, payload: array<string, mixed>}  $attributes
     */
    public function process(Order $order, array $attributes): ProcessedPayment
    {
        $gateway = $this->gatewayManager->gateway($attributes['gateway']);
        $requestHash = $this->requestHash($attributes);

        try {
            $attempt = DB::transaction(function () use ($order, $gateway, $attributes, $requestHash): array {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                $existingPayment = Payment::query()
                    ->where('idempotency_key', $attributes['idempotency_key'])
                    ->first();

                if ($existingPayment instanceof Payment) {
                    if (
                        $existingPayment->order_id !== $lockedOrder->id
                        || $existingPayment->gateway !== $gateway->getGatewayName()
                        || (
                            $existingPayment->request_hash !== null
                            && $existingPayment->request_hash !== $requestHash
                        )
                    ) {
                        throw PaymentBusinessRuleException::idempotencyKeyConflict();
                    }

                    return [$lockedOrder, $existingPayment, true];
                }

                if ($lockedOrder->status !== OrderStatus::Confirmed) {
                    throw PaymentBusinessRuleException::orderNotConfirmed();
                }

                if ($lockedOrder->payments()->where('status', PaymentStatus::Successful)->exists()) {
                    throw PaymentBusinessRuleException::alreadyPaid();
                }

                if ($lockedOrder->payments()->whereIn('status', [
                    PaymentStatus::Pending,
                    PaymentStatus::Processing,
                    PaymentStatus::Unknown,
                ])->exists()) {
                    throw PaymentBusinessRuleException::unresolvedAttempt();
                }

                $payment = $lockedOrder->payments()->create([
                    'payment_reference' => (string) Str::uuid(),
                    'idempotency_key' => $attributes['idempotency_key'],
                    'request_hash' => $requestHash,
                    'gateway' => $gateway->getGatewayName(),
                    'status' => PaymentStatus::Processing,
                    'amount' => $lockedOrder->total_amount,
                ]);

                return [$lockedOrder, $payment, false];
            });
        } catch (QueryException $exception) {
            $existingPayment = Payment::query()
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($existingPayment instanceof Payment) {
                if (
                    $existingPayment->order_id !== $order->id
                    || $existingPayment->gateway !== $gateway->getGatewayName()
                    || (
                        $existingPayment->request_hash !== null
                        && $existingPayment->request_hash !== $requestHash
                    )
                ) {
                    throw PaymentBusinessRuleException::idempotencyKeyConflict();
                }

                return new ProcessedPayment($existingPayment, true);
            }

            throw $exception;
        }

        /** @var array{0: Order, 1: Payment, 2: bool} $attempt */
        [$lockedOrder, $payment, $replayed] = $attempt;

        if ($replayed) {
            return new ProcessedPayment($payment, true);
        }

        try {
            $result = $gateway->charge($lockedOrder, $attributes['payload']);
        } catch (Throwable $exception) {
            report($exception);

            $payment->update([
                'status' => PaymentStatus::Unknown,
                'payload' => [
                    'message' => 'The provider outcome is unknown.',
                ],
                'processed_at' => now(),
            ]);

            throw PaymentGatewayException::outcomeUnknown();
        }

        $payment->update([
            'external_transaction_id' => $result->transactionId,
            'status' => $result->success ? PaymentStatus::Successful : PaymentStatus::Failed,
            'payload' => [
                'response' => $result->rawResponse,
                'message' => $result->message,
            ],
            'processed_at' => now(),
        ]);

        return new ProcessedPayment($payment->refresh(), false);
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

    /**
     * @param  array{gateway: string, payload: array<string, mixed>}  $attributes
     */
    private function requestHash(array $attributes): string
    {
        $request = [
            'gateway' => $attributes['gateway'],
            'payload' => $this->normalizeForHash($attributes['payload']),
        ];

        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->normalizeForHash(...), $value);
        }

        ksort($value);

        return array_map($this->normalizeForHash(...), $value);
    }
}
