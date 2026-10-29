<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Driver;
use App\Models\User;

/** Call from your admin panel (Filament/Nova/etc.). Not exposed over the public API. */
final class DriverReviewService
{
    public function approve(Driver $driver): void
    {
        $driver->forceFill(['reviewed_at' => now(), 'rejection_reason' => null])->save();
        $driver->user->forceFill(['status' => UserStatus::Approved])->save();
    }

    public function reject(Driver $driver, string $reason): void
    {
        $driver->forceFill(['reviewed_at' => now(), 'rejection_reason' => $reason])->save();
        $driver->user->forceFill(['status' => UserStatus::Rejected])->save();
    }

    public function suspend(User $user): void
    {
        if ($user->status === UserStatus::Suspended) {
            return;
        }
        $user->forceFill(['status_before_suspension' => $user->status->value, 'status' => UserStatus::Suspended])->save();
        // Optional: $user->tokens()->delete() to force logout immediately.
    }

    public function unsuspend(User $user): void
    {
        $user->forceFill([
            'status' => $user->status_before_suspension ?? UserStatus::NeedsProfile->value,
            'status_before_suspension' => null,
        ])->save();
    }
}
