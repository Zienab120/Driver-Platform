<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'step1_completed_at' => 'datetime',
            'step2_completed_at' => 'datetime',
            'step3_completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    /** @return array<string,bool> */
    public function stepsCompleted(): array
    {
        return [
            'step1' => $this->step1_completed_at !== null,
            'step2' => $this->step2_completed_at !== null,
            'step3' => $this->step3_completed_at !== null,
        ];
    }

    /** First incomplete step, used by the app to resume. */
    public function nextStep(): ?string
    {
        foreach ($this->stepsCompleted() as $step => $done) {
            if (! $done) {
                return $step;
            }
        }

        return null;
    }
}
