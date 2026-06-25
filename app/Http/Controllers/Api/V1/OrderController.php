<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\ConfirmOrderRequest;
use App\Http\Requests\Orders\DeleteOrderRequest;
use App\Http\Requests\Orders\IndexOrderRequest;
use App\Http\Requests\Orders\ShowOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(IndexOrderRequest $request): JsonResponse
    {
        $orders = $this->orderService->paginate(
            $request->authenticatedUser(),
            $request->validated(),
        );

        return $this->paginatedResponse(
            OrderResource::collection($orders),
            'Orders retrieved successfully.',
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            $request->authenticatedUser(),
            $request->validated(),
        );

        return $this->successResponse(
            OrderResource::make($order),
            'Order created successfully.',
            201,
        );
    }

    public function show(ShowOrderRequest $request, Order $order): JsonResponse
    {
        return $this->successResponse(
            OrderResource::make($this->orderService->show($order)),
            'Order retrieved successfully.',
        );
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        return $this->successResponse(
            OrderResource::make($this->orderService->update($order, $request->validated())),
            'Order updated successfully.',
        );
    }

    public function destroy(DeleteOrderRequest $request, Order $order): JsonResponse
    {
        $this->orderService->delete($order);

        return $this->successResponse(
            new \stdClass,
            'Order deleted successfully.',
        );
    }

    public function confirm(ConfirmOrderRequest $request, Order $order): JsonResponse
    {
        return $this->successResponse(
            OrderResource::make($this->orderService->confirm($order)),
            'Order confirmed successfully.',
        );
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        return $this->successResponse(
            OrderResource::make($this->orderService->cancel($order)),
            'Order cancelled successfully.',
        );
    }
}
