<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\JWTGuard;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * @return array{Authorization: string, Accept: string}
 */
function authHeaders(User $user): array
{
    $guard = auth('api');

    if (! $guard instanceof JWTGuard) {
        throw new LogicException('The API guard must use the JWT driver.');
    }

    return [
        'Authorization' => 'Bearer '.$guard->login($user),
        'Accept' => 'application/json',
    ];
}

/**
 * @return array{Authorization: string, Accept: string, Idempotency-Key: string}
 */
function paymentHeaders(User $user, ?string $idempotencyKey = null): array
{
    return [
        ...authHeaders($user),
        'Idempotency-Key' => $idempotencyKey ?? fake()->uuid(),
    ];
}
