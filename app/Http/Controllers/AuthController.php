<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

final class AuthController
{
    /**
     * @unauthenticated
     */
    public function login(Auth\LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);
        $device = $request->device_name;

        $expiredToken = $this->accessTokenTtlSeconds();
        $refreshTtlMinutes = $this->refreshTokenTtlMinutes();
        $expiredRefreshToken = $this->refreshTokenTtlSeconds();

        try {
            $claims = [
                'device' => $device,
                'type' => 'access',
            ];

            $accessToken = JWTAuth::claims($claims)->attempt($credentials);
            if (! $accessToken) {
                return response()->json([
                    'message' => __('Invalid credentials.'),
                ], 401);
            }

            $refreshToken = $this->makeRefreshToken([
                'device' => $device,
                'type' => 'refresh',
            ], $refreshTtlMinutes);
        } catch (JWTException) {
            return response()->json([
                'message' => __('Unable to create tokens.'),
            ], 500);
        }

        return $this->respondWithTokens($accessToken, $expiredToken, $refreshToken, $expiredRefreshToken);
    }

    /**
     * @unauthenticated
     */
    public function refresh(Auth\RefreshRequest $request): JsonResponse
    {
        try {
            $expiredToken = $this->accessTokenTtlSeconds();
            $expiredRefreshToken = $this->refreshTokenTtlSeconds();

            $payload = JWTAuth::setToken($request->refresh_token)->getPayload();

            if ($payload->get('type') !== 'refresh') {
                return response()->json([
                    'message' => __('Invalid token for refresh.'),
                ], 401);
            }

            $claims = [
                'device' => $payload->get('device'),
                'type' => 'access',
            ];

            $user = JWTAuth::claims($claims)->setToken($request->refresh_token)->toUser();

            // Create new access token
            $accessToken = JWTAuth::fromUser($user);

            $refreshToken = $this->makeRefreshToken([
                'type' => 'refresh',
            ], $expiredRefreshToken);
        } catch (JWTException) {
            return response()->json([
                'message' => __('Invalid or expired refresh token.'),
            ], 401);
        }

        return $this->respondWithTokens($accessToken, $expiredToken, $refreshToken, $expiredRefreshToken);
    }

    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => __('Logout successful.')]);
    }

    private function accessTokenTtlSeconds(): int
    {
        // Use configured TTL in minutes to avoid side effects from factory TTL mutations
        return (int) config('jwt.ttl') * 60;
    }

    private function refreshTokenTtlMinutes(): int
    {
        // Refresh token lasts 7 days based on the base TTL (in minutes)
        return (int) config('jwt.ttl') * 24 * 7;
    }

    private function refreshTokenTtlSeconds(): int
    {
        return $this->refreshTokenTtlMinutes() * 60;
    }

    private function makeRefreshToken(array $claims, int $ttlMinutes): string
    {
        // setTTL expects minutes; do not pass seconds.
        $refreshPayload = JWTFactory::customClaims($claims)
            ->setTTL($ttlMinutes)
            ->make();

        return JWTAuth::encode($refreshPayload)->get();
    }

    private function respondWithTokens(string $accessToken, int $expiresIn, string $refreshToken, int $refreshExpiresIn): JsonResponse
    {
        return response()->json([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn, // seconds
            'refresh_token' => $refreshToken,
            'refresh_expires_in' => $refreshExpiresIn,
            'token_type' => 'bearer',
        ]);
    }
}
