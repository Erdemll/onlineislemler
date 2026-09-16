<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Services\Verification\SmsVerificationCodeSender;
use App\Services\Verification\VerificationCodeService;

class PasswordResetService
{
    public function __construct(
        private VerificationCodeService $verificationCodes,
        private SmsVerificationCodeSender $smsSender,
    ) {}

    public function sendOtp(Customer $customer): void
    {
        $this->verificationCodes->sendVia(
            sender: $this->smsSender,
            customer: $customer,
            purpose: 'password_reset',
            destination: $customer->phone,
        );
    }
}
