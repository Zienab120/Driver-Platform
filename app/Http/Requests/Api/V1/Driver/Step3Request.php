<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;

class Step3Request extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['documents'=>['required','array'],'documents.national_id_front'=>['required','string','max:100'],'documents.national_id_back'=>['required','string','max:100'],'documents.driving_license'=>['required','string','max:100'],'documents.vehicle_license'=>['required','string','max:100'],'documents.criminal_record'=>['required','string','max:100']]; }
}
