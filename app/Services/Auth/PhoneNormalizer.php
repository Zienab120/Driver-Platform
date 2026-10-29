<?php

namespace App\Services\Auth;

use Illuminate\Validation\ValidationException;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/[\s\-()]+/', '', trim($phone));
        if (preg_match('/^01[0125][0-9]{8}$/', $digits)) $digits = '+2' . $digits;
        elseif (preg_match('/^201[0125][0-9]{8}$/', $digits)) $digits = '+' . $digits;
        if (!preg_match('/^\+201[0125][0-9]{8}$/', $digits)) throw ValidationException::withMessages(['phone' => ['Enter a valid Egyptian mobile number.']]);
        return $digits;
    }
}
