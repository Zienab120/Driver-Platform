<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OtpService
{
    public function __construct(
        private readonly SmsGateway $sms,
        private readonly TokenService $tokens,
    ) {}

    /** @return array{expires_in:int, resend_after:int} */
    public function request(string $phone, UserRole $role, string $ip): array
    {
        $existing = User::where('phone', $phone)->first();

        if ($existing?->status === UserStatus::Suspended) {
            throw ApiException::accountSuspended();
        }
        if ($existing && $existing->role !== $role) {
            throw ApiException::roleMismatch();
        }

        // Serialize per phone so concurrent requests cannot bypass the limits.
        Cache::lock("otp:request:{$phone}", 10)->block(5, function () use ($phone, $role, $ip) {
            $this->assertNotRateLimited($phone, $ip);

            $code = str_pad((string) random_int(0, 10 ** config('otp.length') - 1), config('otp.length'), '0', STR_PAD_LEFT);

            OtpCode::create([
                'phone' => $phone,
                'role' => $role,
                'code_hash' => $this->hash($phone, $code),
                'ip' => $ip,
                'expires_at' => now()->addSeconds(config('otp.ttl')),
            ]);

            $this->sms->send($phone, __('otp.message', ['code' => $code, 'minutes' => intdiv(config('otp.ttl'), 60)], 'ar'));
        });

        return ['expires_in' => config('otp.ttl'), 'resend_after' => config('otp.resend_after')];
    }

    /** @return array{user:User, is_new_user:bool, access_token:string, refresh_token:string} */
    public function verify(string $phone, string $code, string $deviceId): array
    {
        // Failed attempts must be persisted, so errors are returned from the transaction and thrown after commit.
        $outcome = DB::transaction(function () use ($phone, $code) {
            $otp = OtpCode::where('phone', $phone)->whereNull('consumed_at')
                ->latest('id')->lockForUpdate()->first();

            if (! $otp || $otp->expires_at->isPast()) {
                return ApiException::otpExpired();
            }

            $max = (int) config('otp.max_attempts');
            if ($otp->attempts >= $max) {
                return ApiException::otpTooManyAttempts();
            }

            if (! $this->codeMatches($otp, $phone, $code)) {
                $otp->increment('attempts');

                return ApiException::otpInvalid(max(0, $max - $otp->attempts));
            }

            // Burn this and any other outstanding codes for the phone.
            OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            $user = User::firstOrCreate(
                ['phone' => $phone],
                ['role' => $otp->role, 'status' => UserStatus::NeedsProfile],
            );

            if ($user->status === UserStatus::Suspended) {
                return ApiException::accountSuspended();
            }
            if ($user->role !== $otp->role) {
                return ApiException::roleMismatch();
            }

            $user->forceFill([
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();

            return [$user, $user->wasRecentlyCreated];
        });

        if ($outcome instanceof ApiException) {
            throw $outcome;
        }

        [$user, $isNew] = $outcome;

        return ['user' => $user, 'is_new_user' => $isNew, ...$this->tokens->issue($user, $deviceId)];
    }

    private function codeMatches(OtpCode $otp, string $phone, string $code): bool
    {
        // Fixed code only ever works outside production.
        if (! app()->isProduction() && config('otp.dev_code_enabled') && hash_equals(config('otp.dev_code'), $code)) {
            return true;
        }

        return hash_equals($otp->code_hash, $this->hash($phone, $code));
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone . '|' . $code, (string) config('app.key'));
    }

    private function assertNotRateLimited(string $phone, string $ip): void
    {
        $latest = OtpCode::where('phone', $phone)->latest('id')->first();
        if ($latest) {
            $wait = $latest->created_at->timestamp + config('otp.resend_after') - now()->timestamp;
            if ($wait > 0) {
                throw ApiException::otpRateLimit($wait);
            }
        }

        $this->guardWindow(OtpCode::where('phone', $phone), config('otp.phone_max'), config('otp.phone_window'));
        $this->guardWindow(OtpCode::where('ip', $ip), config('otp.ip_max'), config('otp.ip_window'));
    }

    private function guardWindow(Builder $base, int $max, int $window): void
    {
        $times = (clone $base)->where('created_at', '>=', now()->subSeconds($window))
            ->orderBy('id')->pluck('created_at');

        if ($times->count() >= $max) {
            // The send that must age out before a slot frees up.
            $blocking = Carbon::parse($times[$times->count() - $max]);
            throw ApiException::otpRateLimit(max(1, $blocking->timestamp + $window - now()->timestamp));
        }
    }
}
