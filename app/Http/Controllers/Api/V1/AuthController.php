<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->authService->register($request->validated()),
            'User registered successfully.',
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $authentication = $this->authService->login($request->validated());

        if ($authentication === null) {
            return $this->errorResponse('Invalid email or password.', 401);
        }

        return $this->successResponse($authentication, 'Authenticated successfully.');
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->successResponse(new \stdClass, 'Logged out successfully.');
    }

    public function refresh(): JsonResponse
    {
        try {
            $authentication = $this->authService->refresh();
        } catch (JWTException) {
            return $this->errorResponse('A valid refreshable token is required.', 401);
        }

        return $this->successResponse($authentication, 'Token refreshed successfully.');
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(
            [
                'user' => $this->authService->user(),
            ],
            'Authenticated user retrieved successfully.',
        );
    }
}
