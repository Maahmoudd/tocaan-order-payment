<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Arr;
use LogicException;
use Tymon\JWTAuth\JWTGuard;

class AuthService
{
    public function __construct(
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     * @return array{user: User, access_token: string, token_type: string, expires_in: int}
     */
    public function register(array $attributes): array
    {
        $user = User::query()->create(Arr::only($attributes, [
            'name',
            'email',
            'password',
        ]));

        return $this->tokenPayload($user, $this->guard()->login($user));
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, access_token: string, token_type: string, expires_in: int}|null
     */
    public function login(array $credentials): ?array
    {
        $token = $this->guard()->attempt($credentials);

        if (! is_string($token)) {
            return null;
        }

        $user = $this->guard()->user();

        if (! $user instanceof User) {
            throw new LogicException('The JWT guard did not return an application user.');
        }

        return $this->tokenPayload($user, $token);
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    /**
     * @return array{user: User, access_token: string, token_type: string, expires_in: int}
     */
    public function refresh(): array
    {
        $guard = $this->guard();
        $token = $guard->refresh();
        $user = $guard->setToken($token)->user();

        if (! $user instanceof User) {
            throw new LogicException('The refreshed JWT does not identify an application user.');
        }

        return $this->tokenPayload($user, $token);
    }

    public function user(): User
    {
        $user = $this->guard()->user();

        if (! $user instanceof User) {
            throw new LogicException('The authenticated JWT does not identify an application user.');
        }

        return $user;
    }

    private function guard(): JWTGuard
    {
        $guard = $this->auth->guard('api');

        if (! $guard instanceof JWTGuard) {
            throw new LogicException('The API guard must use the JWT driver.');
        }

        return $guard;
    }

    /**
     * @return array{user: User, access_token: string, token_type: string, expires_in: int}
     */
    private function tokenPayload(User $user, string $token): array
    {
        return [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ];
    }
}
