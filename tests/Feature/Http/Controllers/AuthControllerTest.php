<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    // Deterministic TTL so we can assert expires_in
    config(['jwt.ttl' => 60]); // minutes

    // Helpers as closures to avoid global function names
    $this->createUser = function (array $overrides = []): User {
        $defaults = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret', // Will be hashed by model cast
        ];

        return User::factory()->create(array_merge($defaults, $overrides));
    };

    $this->login = function (string $email, string $password, string $device) {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => $device,
        ]);
    };

    $this->refresh = function (string $token) {
        return $this->postJson('/api/auth/refresh', [
            'refresh_token' => $token,
        ]);
    };

    $this->logoutWith = function (string $accessToken) {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Accept' => 'application/json',
        ])->deleteJson('/api/auth/logout');
    };
});

// LOGIN: success (no mocks)
it('logs in successfully and returns tokens (integration)', function (): void {
    ($this->createUser)();

    $response = ($this->login)('john@example.com', 'secret', 'iPhone');

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'expires_in',
            'refresh_token',
            'refresh_expires_in',
            'token_type',
        ])
        ->assertJson([
            'token_type' => 'bearer',
            'expires_in' => 60 * 60, // jwt.ttl (60) minutes to seconds
        ]);
});

// LOGIN: invalid credentials (no mocks)
it('fails login with invalid credentials', function (): void {
    // Ensure a user exists but try wrong password
    ($this->createUser)();

    $response = ($this->login)('john@example.com', 'wrong-password', 'Web');

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('Invalid credentials.'),
        ]);
});

// REFRESH: success (no mocks)
it('refreshes token successfully and returns new tokens (integration)', function (): void {
    ($this->createUser)();

    $login = ($this->login)('john@example.com', 'secret', 'Android');
    $login->assertOk();

    $accessToken = $login->json('access_token');
    $refreshToken = $login->json('refresh_token');

    expect($accessToken)->toBeString()->not->toBeEmpty()
        ->and($refreshToken)->toBeString()->not->toBeEmpty();

    $response = ($this->refresh)($refreshToken);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'expires_in',
            'refresh_token',
            'refresh_expires_in',
            'token_type',
        ])
        ->assertJson([
            'token_type' => 'bearer',
            'expires_in' => 60 * 60,
        ]);

    // Access token should change
    expect($response->json('access_token'))
        ->toBeString()
        ->not->toEqual($accessToken);
});

// REFRESH: wrong token type (use access token in place of refresh token)
it('fails to refresh when token type is not refresh (integration)', function (): void {
    ($this->createUser)();

    $login = ($this->login)('john@example.com', 'secret', 'Web');
    $login->assertOk();

    $accessToken = $login->json('access_token'); // this has type=access

    $response = ($this->refresh)($accessToken);

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('Invalid token for refresh.'),
        ]);
});

// LOGOUT: success (no mocks)
it('logs out successfully with bearer token (integration)', function (): void {
    ($this->createUser)();

    $login = ($this->login)('john@example.com', 'secret', 'Web');
    $login->assertOk();

    $accessToken = $login->json('access_token');

    $response = ($this->logoutWith)($accessToken);

    $response->assertOk()
        ->assertJson([
            'message' => __('Logout successful.'),
        ]);
});

it('returns 500 when unable to create tokens on login (JWTException path)', function (): void {
    // Ensure valid user exists
    ($this->createUser)();

    // Switch to asymmetric algorithm without providing keys to force encode failure
    config([
        'jwt.algo' => 'RS256',
        'jwt.keys' => [
            'public' => null,
            'private' => null,
            'passphrase' => null,
        ],
    ]);

    $response = ($this->login)('john@example.com', 'secret', 'Web');

    $response->assertStatus(500)
        ->assertJson([
            'message' => __('Unable to create tokens.'),
        ]);
});

it('returns 401 when refresh token is invalid or malformed (JWTException path)', function (): void {
    // Directly call refresh with a clearly invalid JWT to trigger parsing error
    $response = ($this->refresh)('invalid.token');

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('Invalid or expired refresh token.'),
        ]);
});
