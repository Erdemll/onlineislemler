<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Netgsm\Otp\otp;
use RuntimeException;

class NetgsmSmsService implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        $phone = preg_replace('/^\+90/', '', $phone);

        $data = [
            'message' => "Dogrulama kodunuz: {$code}",
            'no' => $phone,
        ];

        $netgsm = new otp();

        $result = $netgsm->otp($data);

        if (! $result) {
            throw new RuntimeException('OTP SMS gönderilemedi.');
        }
    }
}