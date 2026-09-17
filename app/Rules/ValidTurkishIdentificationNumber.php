<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTurkishIdentificationNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (! preg_match('/^[1-9][0-9]{10}$/', $value)) {
            $fail('Geçerli bir T.C. kimlik numarası giriniz.');

            return;
        }

        $digits = array_map('intval', str_split($value));
        $oddTotal = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenTotal = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $tenthDigit = (($oddTotal * 7) - $evenTotal) % 10;
        $eleventhDigit = array_sum(array_slice($digits, 0, 10)) % 10;

        if ($digits[9] !== $tenthDigit || $digits[10] !== $eleventhDigit) {
            $fail('Geçerli bir T.C. kimlik numarası giriniz.');
        }
    }
}
