<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

class OtpRequestRequest extends PhoneRequest
{
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
