<?php

namespace App\Http\Resources;

use App\Enums\UserStatus;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User (with driver.documents loaded) */
class DriverStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $driver = $this->driver ?? new Driver();
        $status = $this->status;

        return [
            'status' => $status->value,                     // same lifecycle values as /users/me
            'message' => $this->message($status),
            'submitted_at' => $driver->submitted_at?->toIso8601String(),
            'rejection_reason' => $status === UserStatus::Rejected ? $driver->rejection_reason : null,
            'next_step' => $status === UserStatus::NeedsProfile ? $driver->nextStep() : null,
            'steps' => $driver->stepsCompleted(),
            'documents' => $driver->documents->map(fn ($d) => [
                'type' => $d->type->value,
                'status' => $d->status,
                'rejection_reason' => $d->rejection_reason,
            ])->values(),
        ];
    }

    private function message(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::NeedsProfile => 'Please complete your registration.',
            UserStatus::PendingReview => 'Your registration is under review.',
            UserStatus::Approved => 'Your account is approved.',
            UserStatus::Rejected => 'Your registration was rejected.',
            UserStatus::Suspended => 'Your account is suspended. Please contact support.',
            UserStatus::Active => 'Your account is active.',
        };
    }
}
