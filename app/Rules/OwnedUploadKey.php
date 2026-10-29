<?php

namespace App\Rules;

use App\Models\Upload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** The file_key must reference a file that the signed-in user uploaded. */
final class OwnedUploadKey implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $owned = is_string($value)
            && Upload::where('key', $value)->where('user_id', request()->user()?->id)->exists();

        if (! $owned) {
            $fail('The :attribute does not reference one of your uploaded files.');
        }
    }
}
