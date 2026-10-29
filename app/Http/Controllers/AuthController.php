<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefreshTokenRequest;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->tokens->refresh($request->validated('refresh_token'))]);
    }

    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        $this->tokens->logout($request->user(), $request->validated('refresh_token'));

        return response()->json(['data' => null]);
    }
}
