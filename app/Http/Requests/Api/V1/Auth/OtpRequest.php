<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['phone'=>['required','string','max:32'],'role'=>['required','in:passenger,driver']]; }
}
