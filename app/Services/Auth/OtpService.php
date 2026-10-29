<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtpService
{
    public function __construct(private PhoneNormalizer $phones) {}

    public function request(string $rawPhone, string $role, string $ip): array
    {
        $phone = $this->phones->normalize($rawPhone);
        $roleEnum = UserRole::from($role);
        $user = User::where('phone', $phone)->first();
        if ($user && $user->role !== $roleEnum) throw new HttpException(409, 'ROLE_MISMATCH');
        if ($user?->isSuspended()) throw new HttpException(403, 'ACCOUNT_SUSPENDED');

        $recent = OtpChallenge::where('phone', $phone)->where('created_at', '>=', now()->subMinutes(15))->count();
        $ipRecent = OtpChallenge::where('request_ip', $ip)->where('created_at', '>=', now()->subHour())->count();
        $last = OtpChallenge::where('phone', $phone)->latest()->first();
        if ($recent >= 3 || $ipRecent >= 10 || ($last && $last->created_at->gt(now()->subSeconds(60)))) {
            $retry = $last ? max(1, 60 - $last->created_at->diffInSeconds(now())) : 60;
            throw new HttpException(429, 'OTP_RATE_LIMIT|' . $retry);
        }
        $code = app()->environment('production') ? (string) random_int(100000, 999999) : (string) config('auth.otp_dev_code', '123456');
        OtpChallenge::create(['phone' => $phone, 'role' => $roleEnum->value, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5), 'last_sent_at' => now(), 'attempts' => 0, 'request_ip' => $ip]);
        // Replace this local log adapter with your SMS provider. Never log codes in production.
        if (!app()->environment('production')) logger()->info('Development OTP issued', ['phone' => $phone, 'code' => $code]);
        return ['expires_in' => 300, 'resend_after' => 60];
    }

    public function verify(string $rawPhone, string $code, string $deviceId): array
    {
        $phone = $this->phones->normalize($rawPhone);
        $result = DB::transaction(function () use ($phone, $code, $deviceId) {
            $challenge = OtpChallenge::where('phone', $phone)->whereNull('consumed_at')->latest()->lockForUpdate()->first();
            if (!$challenge) return ['_error' => 'OTP_INVALID'];
            if ($challenge->attempts >= 5) return ['_error' => 'OTP_TOO_MANY_ATTEMPTS'];
            if ($challenge->expires_at->isPast()) return ['_error' => 'OTP_EXPIRED'];
            $valid = (!app()->environment('production') && hash_equals((string) config('auth.otp_dev_code', '123456'), $code)) || Hash::check($code, $challenge->code_hash);
            if (!$valid) {
                $challenge->attempts = min(5, $challenge->attempts + 1);
                $challenge->save();
                return ['_error' => $challenge->attempts >= 5 ? 'OTP_TOO_MANY_ATTEMPTS' : 'OTP_INVALID|' . (5 - $challenge->attempts)];
            }
            $user = User::firstOrCreate(['phone' => $phone], ['role' => $challenge->role, 'status' => UserStatus::NeedsProfile->value]);
            if ($user->role->value !== $challenge->role) return ['_error' => 'ROLE_MISMATCH'];
            if ($user->isSuspended()) return ['_error' => 'ACCOUNT_SUSPENDED'];
            $isNew = !$user->phone_verified_at;
            $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'last_login_at' => now()])->save();
            $challenge->forceFill(['consumed_at' => now()])->save();
            $access = $user->createToken('device:' . Str::limit($deviceId, 100, ''), ['*'], now()->addMinutes(15))->plainTextToken;
            $refresh = $this->issueRefreshToken($user, $deviceId);
            return ['access_token' => $access, 'refresh_token' => $refresh, 'is_new_user' => $isNew, 'user' => $this->userPayload($user)];
        });
        if (isset($result['_error'])) {
            $parts = explode('|', $result['_error'], 2);
            throw new HttpException(match ($parts[0]) {
                'OTP_TOO_MANY_ATTEMPTS' => 429,
                'ROLE_MISMATCH' => 409,
                'ACCOUNT_SUSPENDED' => 403,
                default => 400
            }, $result['_error']);
        }
        return $result;
    }

    public function issueRefreshToken(User $user, string $deviceId): string
    {
        $raw = Str::random(80);
        $user->refreshTokens()->create(['device_id' => Str::limit($deviceId, 100, ''), 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->addDays(30)]);
        return $raw;
    }

    public function refresh(string $raw): array
    {
        $hash = hash('sha256', $raw);
        $result = DB::transaction(function () use ($hash) {
            $token = \App\Models\RefreshToken::where('token_hash', $hash)->lockForUpdate()->first();
            if (!$token || $token->revoked_at || $token->expires_at->isPast()) {
                if ($token && $token->revoked_at) {
                    \App\Models\RefreshToken::where('user_id', $token->user_id)->where('device_id', $token->device_id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                    return ['_error' => 'REFRESH_INVALID'];
                }
                return ['_error' => 'REFRESH_INVALID'];
            }
            $user = $token->user;
            if ($user->isSuspended()) return ['_error' => 'ACCOUNT_SUSPENDED'];
            $token->forceFill(['revoked_at' => now()])->save();
            $newRefresh = $this->issueRefreshToken($user, $token->device_id);
            $newRow = \App\Models\RefreshToken::where('token_hash', hash('sha256', $newRefresh))->first();
            $token->forceFill(['replaced_by_id' => $newRow->id])->save();
            $access = $user->createToken('device:' . $token->device_id, ['*'], now()->addMinutes(15))->plainTextToken;
            return ['access_token' => $access, 'refresh_token' => $newRefresh];
        });
        if (isset($result['_error'])) throw new HttpException($result['_error'] === 'ACCOUNT_SUSPENDED' ? 403 : 401, $result['_error']);
        return $result;
    }

    public function logout(User $user, string $raw): void
    {
        $token = \App\Models\RefreshToken::where('user_id', $user->id)->where('token_hash', hash('sha256', $raw))->first();
        if ($token && !$token->revoked_at) $token->forceFill(['revoked_at' => now()])->save();
        $user->currentAccessToken()?->delete();
    }

    public function userPayload(User $user): array
    {
        $profile = $user->driverProfile;
        return ['id' => (string)$user->id, 'phone' => $user->phone, 'role' => $user->role->value, 'full_name' => $user->full_name ?? $profile?->full_name, 'avatar_url' => null, 'status' => $user->status->value];
    }
}
