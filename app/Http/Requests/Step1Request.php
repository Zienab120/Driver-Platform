<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Rules\OwnedUploadKey;
use App\Support\Digits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role + account checks are middleware
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('national_id'))) {
            $this->merge(['national_id' => Digits::toLatin(trim($this->input('national_id')))]);
        }
    }

    public function rules(): array
    {
        $oldest = now()->subYears((int) config('onboarding.min_driver_age'))->toDateString();

        return [
            'phone' => ['prohibited'], // phone always comes from the authenticated user
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'national_id' => ['required', 'string', 'regex:/^\d{14}$/'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'after:1900-01-01', "before_or_equal:{$oldest}"],
            'gender' => ['required', Rule::enum(Gender::class)],
            'avatar_key' => ['nullable', 'string', 'max:255', new OwnedUploadKey],
            'district_id' => ['nullable', 'integer', 'min:1'], // add exists:districts,id when that table exists
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.regex' => 'The national ID must be exactly 14 digits.',
            'birth_date.before_or_equal' => 'The driver must be at least ' . config('onboarding.min_driver_age') . ' years old.',
        ];
    }
}
