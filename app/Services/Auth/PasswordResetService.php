<?php

namespace App\Services\Auth;

use App\Contracts\SmsSender;
use App\Models\Customer;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;

class PasswordResetService
{
    public function __construct(
        private SmsSender $sms
    ) {}

    public function sendOtp(Customer $customer): void
    {
        OtpVerification::where(
            'customer_id',
            $customer->id
        )
            ->where(
                'purpose',
                'password_reset'
            )
            ->whereNull('verified_at')
            ->delete();

        $code = (string) random_int(
            100000,
            999999
        );

        OtpVerification::create([
            'customer_id' => $customer->id,
            'purpose' => 'password_reset',
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