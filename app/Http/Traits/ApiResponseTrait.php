<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponseTrait
{
    protected function successResponse(
        mixed $data,
        string $message,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
            'meta' => $meta === [] ? new \stdClass : $meta,
        ], $status);
    }

    protected function paginatedResponse(
        AnonymousResourceCollection $collection,
        string $message,
    ): JsonResponse {
        $response = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $response['data'],
            'meta' => [
                'current_page' => $response['meta']['current_page'],
                'from' => $response['meta']['from'],
                'last_page' => $response['meta']['last_page'],
                'path' => $response['meta']['path'],
                'per_page' => $response['meta']['per_page'],
                'to' => $response['meta']['to'],
                'total' => $response['meta']['total'],
            ],
        ]);
    }

    protected function errorResponse(
        string $message,
        int $status,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? new \stdClass : $errors,
        ], $status);
    }
}
