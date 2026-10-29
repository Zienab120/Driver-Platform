<?php

namespace App\Support;

final class EgyptianPhone
{
    /** Returns +201XXXXXXXXX for 010/011/012/015 numbers (local or international), else null. */
    public static function normalize(string $input): ?string
    {
        $s = preg_replace('/[\s\-().]/', '', Digits::toLatin($input));

        if (preg_match('/^(?:\+20|0020|20|0)(1[0125]\d{8})$/', $s, $m)) {
            return '+20' . $m[1];
        }

        return null;
    }
}
