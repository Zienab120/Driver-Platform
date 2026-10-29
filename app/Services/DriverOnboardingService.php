<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DriverOnboardingService
{
    public function saveStep1(User $user, array $data): void
    {
        $this->assertEditable($user);
        $driver = $this->driverFor($user);

        try {
            DB::transaction(function () use ($user, $driver, $data) {
                $driver->fill([
                    'national_id' => $data['national_id'],
                    'birth_date' => $data['birth_date'],
                    'gender' => $data['gender'],
                    'district_id' => $data['district_id'] ?? null,
                ]);
                $driver->step1_completed_at = now();
                $driver->save();

                $user->forceFill(['full_name' => $data['full_name'], 'avatar_key' => $data['avatar_key']])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::nationalIdTaken();
        }
    }

    public function saveStep2(User $user, array $data): void
    {
        $this->assertEditable($user);
        $driver = $this->driverFor($user);

        try {
            DB::transaction(function () use ($driver, $data) {
                $driver->fill([
                    'plate_numbers' => $data['plate_numbers'],
                    'plate_letters' => $data['plate_letters'],
                    'model' => $data['model'],
                    'color' => $data['color'],
                ]);
                $driver->step2_completed_at = now();
                $driver->save();

                $this->syncDocuments($driver, $data['documents']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::plateTaken();
        }
    }

    public function saveStep3(User $user, array $data): void
    {
        $this->assertEditable($user);
        $driver = $this->driverFor($user);

        DB::transaction(function () use ($driver, $data) {
            $this->syncDocuments($driver, $data['documents']);
            $driver->forceFill(['step3_completed_at' => now()])->save();
        });
    }

    public function submit(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Lock the user row so double-taps cannot submit twice.
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::NeedsProfile) {
                throw ApiException::applicationLocked();
            }

            $driver = $this->driverFor($locked)->load('documents');

            $missingSteps = array_keys(array_filter($driver->stepsCompleted(), fn ($done) => ! $done));
            $have = $driver->documents->map(fn ($d) => $d->type->value)->all();
            $required = DocumentType::values([...DocumentType::vehiclePhotos(), ...DocumentType::identityDocuments()]);
            $missingDocs = array_values(array_diff($required, $have));

            if ($missingSteps || $missingDocs) {
                throw ApiException::applicationIncomplete($missingSteps, $missingDocs);
            }

            // Eligibility: age is derived from birth_date at submit time.
            if ($driver->birth_date->age < config('onboarding.min_driver_age')) {
                throw ApiException::applicationIncomplete(['step1'], []);
            }

            $driver->forceFill(['submitted_at' => now(), 'rejection_reason' => null, 'reviewed_at' => null])->save();
            $locked->forceFill(['status' => UserStatus::PendingReview])->save();
        });

        $user->refresh();
    }

    /** Steps are only editable while the driver is in needs_profile. */
    private function assertEditable(User $user): void
    {
        if ($user->status !== UserStatus::NeedsProfile) {
            throw ApiException::applicationLocked();
        }
    }

    private function driverFor(User $user): Driver
    {
        return Driver::firstOrCreate(['user_id' => $user->id]);
    }

    /** @param list<array{type:string,file_key:string}> $documents */
    private function syncDocuments(Driver $driver, array $documents): void
    {
        foreach ($documents as $doc) {
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id, 'type' => $doc['type']],
                ['file_key' => $doc['file_key'], 'status' => 'pending', 'rejection_reason' => null, 'reviewed_at' => null],
            );
        }
    }
}
