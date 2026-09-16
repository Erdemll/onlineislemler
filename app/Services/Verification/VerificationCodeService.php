<?php

namespace App\Services\Verification;

use App\Contracts\VerificationCodeSender;
use App\Models\Customer;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationCodeService
{
    public function __construct(
        private VerificationCodeSender $sender
    ) {}

    public function send(
        Customer $customer,
        string $purpose,
        string $destination
    ): void {
        $this->sendVia(
            sender: $this->sender,
            customer: $customer,
            purpose: $purpose,
            destination: $destination,
        );
    }

    public function sendVia(
        VerificationCodeSender $sender,
        Customer $customer,
        string $purpose,
        string $destination
    ): void {
        DB::transaction(function () use ($sender, $customer, $purpose, $destination): void {
            OtpVerification::where(
                'customer_id',
                $customer->id
            )
                ->where(
                    'purpose',
                    $purpose
                )
                ->whereNull('verified_at')
                ->delete();

            $code = (string) random_int(
                100000,
                999999
            );

            OtpVerification::create([
                'customer_id' => $customer->id,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
            ]);

            $sender->send(
                destination: $destination,
                code: $code,
                purpose: $purpose,
            );
        });
    }

    public function verify(
        Customer $customer,
        string $purpose,
        string $code
    ): bool {
        return DB::transaction(function () use ($customer, $purpose, $code): bool {
            $verification = OtpVerification::where(
                'customer_id',
                $customer->id
            )
                ->where(
                    'purpose',
                    $purpose
                )
                ->whereNull('verified_at')
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $verification) {
                return false;
            }

            if ($verification->expires_at->isPast()) {
                return false;
            }

            if ($verification->attempts >= 5) {
                return false;
            }

            if (! Hash::check($code, $verification->code_hash)) {
                $verification->increment('attempts');

                return false;
            }

            $verification->forceFill([
                'verified_at' => now(),
            ])->save();

            return true;
        });
    }
}
