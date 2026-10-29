<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Rules\OwnedUploadKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step3Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'size:' . count(DocumentType::identityDocuments())],
            'documents.*.type' => ['required', 'distinct', Rule::in(DocumentType::values(DocumentType::identityDocuments()))],
            'documents.*.file_key' => ['required', 'string', 'max:255', new OwnedUploadKey],
        ];
    }

    public function messages(): array
    {
        return ['documents.size' => 'All five documents are required.'];
    }
}
