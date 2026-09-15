<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\OtpVerification;
use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Hash;

class PhoneVerificationService
{
    public function __construct(
        private SmsSender $sms
    ) {
    }

    public function send(Customer $customer): void
    {
        OtpVerification::where(
            'customer_id',
            $customer->id
        )
            ->where(
                'purpose',
                'phone_verification'
            )
            ->whereNull('verified_at')
            ->delete();

        $code = (string) random_int(
            100000,
            999999
        );

        OtpVerification::create([
            'customer_id' => $customer->id,

            'purpose' => 'phone_verification',

            'code_hash' => Hash::make($code),

            'attempts' => 0,

            'expires_at' => now()->addMinutes(5),
        ]);

        $this->sms->sendOtp(
            $customer->phone,
            $code
        );
    }
}