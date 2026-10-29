<?php

namespace App\Http\Requests;

use App\Support\Digits;

class OtpVerifyRequest extends PhoneRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('code'))) {
            $this->merge(['code' => Digits::toLatin(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
            'code' => ['required', 'digits:6'],
            'device_id' => ['required', 'string', 'max:191'],
        ];
    }
}
