<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\VehicleColor;
use App\Rules\OwnedUploadKey;
use App\Support\Digits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $in = $this->all();

        if (is_string($in['plate_numbers'] ?? null)) {
            $in['plate_numbers'] = Digits::toLatin(trim($in['plate_numbers']));
        }
        // "ق ن ص" and "قنص" are the same plate: store without whitespace.
        if (is_string($in['plate_letters'] ?? null)) {
            $in['plate_letters'] = preg_replace('/\s+/u', '', $in['plate_letters']);
        }
        // Spec examples use vehicle_back, the data model uses vehicle_rear. Accept both.
        if (is_array($in['documents'] ?? null)) {
            $in['documents'] = array_map(function ($d) {
                if (is_array($d) && ($d['type'] ?? null) === 'vehicle_back') {
                    $d['type'] = 'vehicle_rear';
                }

                return $d;
            }, $in['documents']);
        }

        $this->merge($in);
    }

    public function rules(): array
    {
        return [
            'plate_numbers' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'plate_letters' => ['required', 'string', 'regex:/^\p{Arabic}{2,3}$/u'],
            'model' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::enum(VehicleColor::class)],
            'documents' => ['required', 'array', 'size:3'],
            'documents.*.type' => ['required', 'distinct', Rule::in(DocumentType::values(DocumentType::vehiclePhotos()))],
            'documents.*.file_key' => ['required', 'string', 'max:255', new OwnedUploadKey],
        ];
    }

    public function messages(): array
    {
        return [
            'plate_numbers.regex' => 'Plate numbers must be 1 to 4 digits.',
            'plate_letters.regex' => 'Plate letters must be 2 or 3 Arabic letters.',
            'documents.size' => 'Front, rear and interior photos are all required.',
        ];
    }
}
