<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

beforeEach(function (): void {
    // Ensure no middleware interferes (e.g., auth:api) when we don't need it
    $this->withoutMiddleware();

    // Use closures instead of global helper functions to avoid name collisions
    $this->mockTtl = function (int $ttlMinutes = 60): void {
        // JWTAuth::factory()->getTTL() => $ttlMinutes
        $factoryMock = Mockery::mock();
        $factoryMock->shouldReceive('getTTL')->andReturn($ttlMinutes);

        JWTAuth::shouldReceive('factory')->andReturn($factoryMock);
    };

    $this->mockRefreshTokenPipeline = function (string $refreshToken = 'refresh.token.example', int $ttlSeconds = 60 * 60 * 24 * 7): void {
        // JWTFactory::customClaims(...)->setTTL($ttlSeconds)->make() => $payload
        $payload = Mockery::mock();

        JWTFactory::shouldReceive('customClaims')->andReturnSelf();
        JWTFactory::shouldReceive('setTTL')->with($ttlSeconds)->andReturnSelf();
        JWTFactory::shouldReceive('make')->andReturn($payload);

        // JWTAuth::encode($payload)->get() => $refreshToken
        $encoded = Mockery::mock();
        $encoded->shouldReceive('get')->andReturn($refreshToken);
        JWTAuth::shouldReceive('encode')->with($payload)->andReturn($encoded);
    };
});

// LOGIN: success
it('logs in successfully and returns tokens', function (): void {
    ($this->mockTtl)(60); // 60 minutes

    $expiresIn = 60 * 60; // 3600 seconds
    $refreshExpiresIn = 60 * 60 * 24 * 7; // 7 days in seconds (as used by controller)

    // Mock login attempt
    JWTAuth::shouldReceive('claims')->with(Mockery::on(function ($claims) {
        return $claims['device'] === 'iPhone' && $claims['type'] === 'access';
    }))->andReturnSelf();

    JWTAuth::shouldReceive('attempt')->with([
        'email' => 'john@example.com',
        'password' => 'secret',
    ])->andReturn('access.token.example');

    ($this->mockRefreshTokenPipeline)('refresh.token.example', $refreshExpiresIn);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'john@example.com',
        'password' => 'secret',
        'device_name' => 'iPhone',
    ]);

    $response->assertOk()
        ->assertJson([
            'access_token' => 'access.token.example',
            'expires_in' => $expiresIn,
            'refresh_token' => 'refresh.token.example',
            'refresh_expires_in' => $refreshExpiresIn,
            'token_type' => 'bearer',
        ]);
});

// LOGIN: invalid credentials
it('fails login with invalid credentials', function (): void {
    ($this->mockTtl)(60);

    JWTAuth::shouldReceive('claims')->andReturnSelf();
    JWTAuth::shouldReceive('attempt')->andReturnFalse();

    $response = $this->postJson('/api/auth/login', [
        'email' => 'bad@example.com',
        'password' => 'wrong',
        'device_name' => 'Web',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('Invalid credentials.'),
        ]);
});

// LOGIN: exception when creating tokens
it('handles exception during login token creation', function (): void {
    ($this->mockTtl)(60);

    JWTAuth::shouldReceive('claims')->andReturnSelf();
    JWTAuth::shouldReceive('attempt')->andThrow(new JWTException('boom'));

    $response = $this->postJson('/api/auth/login', [
        'email' => 'john@example.com',
        'password' => 'secret',
        'device_name' => 'Web',
    ]);

    $response->assertStatus(500)
        ->assertJson([
            'message' => __('Unable to create tokens.'),
        ]);
});

// REFRESH: success
it('refreshes token successfully and returns new tokens', function (): void {
    ($this->mockTtl)(60);

    $expiresIn = 60 * 60;
    $refreshExpiresIn = 60 * 60 * 24 * 7;

    $token = 'old.refresh.token';

    // setToken(token)->getPayload() => payload with type refresh and device Android
    $payload = Mockery::mock();
    $payload->shouldReceive('get')->with('type')->andReturn('refresh');
    $payload->shouldReceive('get')->with('device')->andReturn('Android');

    // Use the facade self for the chain to support both getPayload and toUser later
    JWTAuth::shouldReceive('setToken')->with($token)->andReturnSelf();
    JWTAuth::shouldReceive('getPayload')->andReturn($payload);

    // claims([...])->setToken(token)->toUser() => User
    JWTAuth::shouldReceive('claims')->with(Mockery::on(function ($claims) {
        return $claims['device'] === 'Android' && $claims['type'] === 'access';
    }))->andReturnSelf();

    $user = new User(['name' => 'John', 'email' => 'john@example.com']);

    JWTAuth::shouldReceive('toUser')->andReturn($user);

    // fromUser(user) => new access token
    JWTAuth::shouldReceive('fromUser')->with($user)->andReturn('new.access.token');

    ($this->mockRefreshTokenPipeline)('new.refresh.token', $refreshExpiresIn);

    $response = $this->postJson('/api/auth/refresh', [
        'token' => $token,
    ]);

    $response->assertOk()
        ->assertJson([
            'access_token' => 'new.access.token',
            'expires_in' => $expiresIn,
            'refresh_token' => 'new.refresh.token',
            'refresh_expires_in' => $refreshExpiresIn,
            'token_type' => 'bearer',
        ]);
});

// REFRESH: wrong token type
it('fails to refresh when token type is not refresh', function (): void {
    ($this->mockTtl)(60);

    $token = 'some.token';

    $payload = Mockery::mock();
    $payload->shouldReceive('get')->with('type')->andReturn('access'); // wrong type

    $jwtSetTokenMock = Mockery::mock();
    $jwtSetTokenMock->shouldReceive('getPayload')->andReturn($payload);

    JWTAuth::shouldReceive('setToken')->with($token)->andReturn($jwtSetTokenMock);

    $response = $this->postJson('/api/auth/refresh', [
        'token' => $token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('Invalid token for refresh.'),
        ]);
});

// REFRESH: exception path
it('handles exception during refresh', function (): void {
    ($this->mockTtl)(60);

    JWTAuth::shouldReceive('setToken')->andThrow(new JWTException('invalid'));

    $response = $this->postJson('/api/auth/refresh', [
        'token' => 'bad.token',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('Invalid or expired refresh token.'),
        ]);
});

// LOGOUT: success message
it('logs out successfully', function (): void {
    // Mock auth()->logout() to avoid dependency on JWT parsing in the guard
    Auth::shouldReceive('logout')->andReturnNull();

    $response = $this->deleteJson('/api/auth/logout');

    $response->assertOk()
        ->assertJson([
            'message' => __('Logout successful.'),
        ]);
});
