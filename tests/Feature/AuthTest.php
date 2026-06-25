<?php

use App\Exceptions\AuthBusinessRuleException;
use App\Models\User;
use App\Services\AuthService;

it('registers a user and returns a JWT', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'USER@EXAMPLE.COM',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'user@example.com')
        ->assertJsonStructure([
            'data' => ['user', 'access_token', 'token_type', 'expires_in'],
        ]);

    $this->assertDatabaseHas('users', ['email' => 'user@example.com']);
});

it('validates registration input using the error envelope', function () {
    $this->postJson('/api/v1/register', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'errors' => ['name', 'email', 'password']]);
});

it('logs in and returns the authenticated user', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => 'password',
    ]);

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('data.access_token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'invalid@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'invalid@example.com',
        'password' => 'incorrect',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});

it('refreshes and invalidates JWTs on logout', function () {
    $headers = authHeaders(User::factory()->create());
    $originalToken = str_replace('Bearer ', '', $headers['Authorization']);

    $refresh = $this->withToken($originalToken)
        ->postJson('/api/v1/refresh')
        ->assertOk();

    $refreshedToken = $refresh->json('data.access_token');

    expect($refreshedToken)->not->toBe($originalToken);

    auth()->forgetGuards();
    $this->withToken($refreshedToken)->getJson('/api/v1/me')->assertOk();

    auth()->forgetGuards();
    $this->withToken($refreshedToken)->postJson('/api/v1/logout')->assertOk();

    auth()->forgetGuards();
    $this->withToken($refreshedToken)->getJson('/api/v1/me')->assertUnauthorized();
});

it('rate limits repeated authentication attempts using the API error envelope', function () {
    for ($attempt = 1; $attempt <= 11; $attempt++) {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->postJson('/api/v1/login', [
                'email' => 'missing@example.com',
                'password' => 'incorrect',
            ]);
    }

    $response
        ->assertTooManyRequests()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Too many requests. Please try again later.');
});

it('converts a duplicate email database violation into a domain exception', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    app(AuthService::class)->register([
        'name' => 'Duplicate User',
        'email' => 'duplicate@example.com',
        'password' => 'password',
    ]);
})->throws(AuthBusinessRuleException::class, 'already exists');
