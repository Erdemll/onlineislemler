<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', $phone);

        // 05551112233
        if (
            str_starts_with($phone, '0')
            && strlen($phone) === 11
        ) {
            $phone = substr($phone, 1);
        }

        // 905551112233
        if (
            str_starts_with($phone, '90')
            && strlen($phone) === 12
        ) {
            $phone = substr($phone, 2);
        }

        // 5551112233
        if (strlen($phone) === 10) {
            return '+90'.$phone;
        }

        return '+'.$phone;
    }
}