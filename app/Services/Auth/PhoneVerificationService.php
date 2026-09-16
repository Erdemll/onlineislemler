<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Services\Verification\SmsVerificationCodeSender;
use App\Services\Verification\VerificationCodeService;

class PhoneVerificationService
{
    public function __construct(
        private VerificationCodeService $verificationService,
        private SmsVerificationCodeSender $smsSender,
    ) {}

    public function send(Customer $customer): void
    {
        $this->verificationService->sendVia(
            sender: $this->smsSender,
            customer: $customer,
            purpose: 'phone_verification',
            destination: $customer->phone,
        );
    }
}
