<?php

namespace App\Http\Requests;

use App\Support\EgyptianPhone;
use Illuminate\Foundation\Http\FormRequest;

abstract class PhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone)) {
            $this->merge(['phone' => EgyptianPhone::normalize($phone) ?? $phone]);
        }
    }

    protected function phoneRules(): array
    {
        return ['required', 'string', 'regex:/^\+201[0125]\d{8}$/'];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid Egyptian mobile number (010, 011, 012 or 015).'];
    }
}
