<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['phone'=>['required','string','max:32'],'code'=>['required','digits:6'],'device_id'=>['required','string','min:1','max:100']]; }
}
