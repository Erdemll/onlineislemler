<?php

namespace App\Services\Verification;

use App\Contracts\SmsSender;
use App\Contracts\VerificationCodeSender;

class SmsVerificationCodeSender implements VerificationCodeSender
{
    public function __construct(
        private SmsSender $sms
    ) {}

    public function send(
        string $destination,
        string $code,
        string $purpose
    ): void {
        $this->sms->sendOtp($destination, $code);
    }
}
