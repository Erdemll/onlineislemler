<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Services\Verification\VerificationCodeService;

class PasswordResetService
{
    public function __construct(
        private VerificationCodeService $verificationCodes,
    ) {}

    public function sendOtp(Customer $customer): void
    {
        $this->verificationCodes->send(
            customer: $customer,
            purpose: 'password_reset',
            destination: $customer->email,
        );
    }
}
