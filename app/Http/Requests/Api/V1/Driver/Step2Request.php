<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step2Request extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['plate_numbers'=>['required','regex:/^[0-9]{1,4}$/'],'plate_letters'=>['required','regex:/^[\x{0621}-\x{064A}]{2,3}$/u'],'model'=>['required','string','max:100'],'color'=>['required',Rule::in(['أبيض','أسود','فضي','رمادي','أزرق','أحمر','أخضر','بيج','ذهبي','أخرى'])],'documents'=>['required','array'],'documents.vehicle_front'=>['required','string','max:100'],'documents.vehicle_back'=>['required','string','max:100'],'documents.vehicle_interior'=>['required','string','max:100']]; }
}
