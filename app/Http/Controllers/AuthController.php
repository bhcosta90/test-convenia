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
    public function __construct() {}

    public function login(Auth\LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);
        $device = $request->device_name;
        $expiredToken = JWTAuth::factory()->getTTL() * 60;
        $expiredRefreshToken = JWTAuth::factory()->getTTL() * 60 * 24 * 7;

        try {
            $claims = [
                'device' => $device,
                'type' => 'access',
            ];
            if (! $accessToken = JWTAuth::claims($claims)->attempt($credentials)) {
                return response()->json([
                    'message' => 'Credenciais inválidas.',
                ], 401);
            }

            $refreshPayload = JWTFactory::customClaims([
                'device' => $device,
                'type' => 'refresh',
            ])->setTTL($expiredRefreshToken) // 7 dias
                ->make();

            $refreshToken = JWTAuth::encode($refreshPayload)->get();

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Não foi possível criar os tokens.',
            ], 500);
        }

        return response()->json([
            'access_token' => $accessToken,
            'expires_in' => $expiredToken, // segundos
            'refresh_token' => $refreshToken,
            'refresh_expires_in' => $expiredRefreshToken,
            'token_type' => 'bearer',
        ]);
    }

    public function refresh(Auth\RefreshRequest $request): JsonResponse
    {
        try {
            $expiredToken = JWTAuth::factory()->getTTL() * 60;
            $expiredRefreshToken = JWTAuth::factory()->getTTL() * 60 * 24 * 7;

            $payload = JWTAuth::setToken($request->token)->getPayload();

            if ($payload->get('type') !== 'refresh') {
                return response()->json([
                    'message' => 'Token inválido para refresh.',
                ], 401);
            }

            $claims = [
                'device' => $payload->get('device'),
                'type' => 'access',
            ];

            $user = JWTAuth::claims($claims)->setToken($request->token)->toUser();

            // Cria novo access token
            $accessToken = JWTAuth::fromUser($user);

            $refreshPayload = JWTFactory::customClaims([
                'type' => 'refresh',
            ])->setTTL($expiredRefreshToken) // 7 dias
                ->make();

            $refreshToken = JWTAuth::encode($refreshPayload)->get();

        } catch (JWTException) {
            return response()->json([
                'message' => 'Token de refresh inválido ou expirado.',
            ], 401);
        }

        return response()->json([
            'access_token' => $accessToken,
            'expires_in' => $expiredToken, // segundos
            'refresh_token' => $refreshToken,
            'refresh_expires_in' => $expiredRefreshToken,
            'token_type' => 'bearer',
        ]);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }
}
