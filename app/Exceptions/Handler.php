<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*'),
        );

        $this->renderable(
            fn (AuthenticationException $exception, Request $request): ?JsonResponse => $this->apiError(
                $request,
                $exception->getMessage(),
                401,
            ),
        );

        $this->renderable(
            fn (AuthorizationException $exception, Request $request): ?JsonResponse => $this->apiError(
                $request,
                'You are not authorized to perform this action.',
                403,
            ),
        );

        $this->renderable(
            fn (OrderBusinessRuleException $exception, Request $request): ?JsonResponse => $this->apiError(
                $request,
                $exception->getMessage(),
                409,
            ),
        );

        $this->renderable(
            fn (ModelNotFoundException|NotFoundHttpException $exception, Request $request): ?JsonResponse => $this->apiError(
                $request,
                'The requested resource was not found.',
                404,
            ),
        );

        $this->renderable(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        });
    }

    private function apiError(Request $request, string $message, int $status): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => new \stdClass,
        ], $status);
    }
}
