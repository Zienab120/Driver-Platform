<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Access tokens: Sanctum (15 min). Refresh tokens: opaque, hashed, rotated, 30 days, reuse-detected. */
final class TokenService
{
    private const ACCESS_TTL_MINUTES = 15;
    private const REFRESH_TTL_DAYS = 30;

    /** @return array{access_token:string, refresh_token:string} */
    public function issue(User $user, string $deviceId): array
    {
        // One live session per device.
        $user->tokens()->where('name', $this->name($deviceId))->delete();

        $access = $user->createToken($this->name($deviceId), ['*'], now()->addMinutes(self::ACCESS_TTL_MINUTES));

        $refresh = bin2hex(random_bytes(32));
        RefreshToken::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => hash('sha256', $refresh),
            'expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
        ]);

        return ['access_token' => $access->plainTextToken, 'refresh_token' => $refresh];
    }

    /** @return array{access_token:string, refresh_token:string} */
    public function refresh(string $plain): array
    {
        // Commit revocations even when we end up rejecting the request, so throw after the transaction.
        $result = DB::transaction(function () use ($plain) {
            $row = RefreshToken::where('token_hash', hash('sha256', $plain))->lockForUpdate()->first();

            if (! $row) {
                return null;
            }

            if ($row->used_at || $row->revoked_at) {
                // Reuse of a rotated/revoked token: assume theft, kill the device's session.
                $this->revokeDevice($row->user_id, $row->device_id);

                return null;
            }

            if ($row->expires_at->isPast()) {
                return null;
            }

            $row->forceFill(['used_at' => now()])->save();

            return $this->issue($row->user, $row->device_id);
        });

        return $result ?? throw ApiException::refreshInvalid();
    }

    public function logout(User $user, string $plainRefresh): void
    {
        $row = RefreshToken::where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $plainRefresh))
            ->first();

        if ($row) {
            $this->revokeDevice($user->id, $row->device_id);
        }

        $user->currentAccessToken()?->delete();
    }

    private function revokeDevice(int $userId, string $deviceId): void
    {
        RefreshToken::where('user_id', $userId)->where('device_id', $deviceId)
            ->whereNull('revoked_at')->update(['revoked_at' => now()]);

        User::find($userId)?->tokens()->where('name', $this->name($deviceId))->delete();
    }

    private function name(string $deviceId): string
    {
        return 'device:' . $deviceId;
    }
}
