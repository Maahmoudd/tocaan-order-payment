<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\IndexOrderPaymentRequest;
use App\Http\Requests\Payments\IndexPaymentRequest;
use App\Http\Requests\Payments\ShowPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        $payment = $this->paymentService->process($order, $request->validated());

        return $this->successResponse(
            PaymentResource::make($payment),
            $payment->status === PaymentStatus::Successful
                ? 'Payment processed successfully.'
                : 'Payment processing failed.',
            201,
        );
    }

    public function forOrder(IndexOrderPaymentRequest $request, Order $order): JsonResponse
    {
        $payments = $this->paymentService->paginateForOrder($order, $request->validated());

        return $this->paginatedResponse(
            PaymentResource::collection($payments),
            'Order payments retrieved successfully.',
        );
    }

    public function index(IndexPaymentRequest $request): JsonResponse
    {
        $payments = $this->paymentService->paginateForUser(
            $request->authenticatedUser(),
            $request->validated(),
        );

        return $this->paginatedResponse(
            PaymentResource::collection($payments),
            'Payments retrieved successfully.',
        );
    }

    public function show(ShowPaymentRequest $request, Payment $payment): JsonResponse
    {
        return $this->successResponse(
            PaymentResource::make($this->paymentService->show($payment)),
            'Payment retrieved successfully.',
        );
    }
}
