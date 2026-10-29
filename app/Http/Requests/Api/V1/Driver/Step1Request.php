<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step1Request extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['full_name'=>['required','string','min:3','max:150'],'national_id'=>['required','regex:/^[0-9]{14}$/'],'birth_date'=>['required','date_format:Y-m-d','before_or_equal:'.now()->subYears(18)->toDateString()],'gender'=>['required',Rule::in(['male','female'])],'avatar_key'=>['required','string','max:100']]; }
}
