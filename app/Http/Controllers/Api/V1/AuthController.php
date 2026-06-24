<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data' => $this->authService->register($request->validated()),
            'meta' => new \stdClass,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $authentication = $this->authService->login($request->validated());

        if ($authentication === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
                'errors' => new \stdClass,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'data' => $authentication,
            'meta' => new \stdClass,
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => new \stdClass,
            'meta' => new \stdClass,
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            $authentication = $this->authService->refresh();
        } catch (JWTException) {
            return response()->json([
                'success' => false,
                'message' => 'A valid refreshable token is required.',
                'errors' => new \stdClass,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data' => $authentication,
            'meta' => new \stdClass,
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => [
                'user' => $this->authService->user(),
            ],
            'meta' => new \stdClass,
        ]);
    }
}
