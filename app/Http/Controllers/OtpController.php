<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\OtpRequestRequest;
use App\Http\Requests\OtpVerifyRequest;
use App\Http\Resources\UserResource;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function request(OtpRequestRequest $request): JsonResponse
    {
        $data = $this->otp->request(
            $request->validated('phone'),
            UserRole::from($request->validated('role')),
            (string) $request->ip(),
        );

        return response()->json(['data' => $data]);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $result = $this->otp->verify(
            $request->validated('phone'),
            $request->validated('code'),
            $request->validated('device_id'),
        );

        return response()->json(['data' => [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'is_new_user' => $result['is_new_user'],
            'user' => UserResource::make($result['user'])->resolve(),
        ]]);
    }
}
