<?php

namespace App\Services\Verification;

use App\Contracts\VerificationCodeSender;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;

class EmailVerificationCodeSender implements VerificationCodeSender
{
    public function send(
        string $destination,
        string $code,
        string $purpose
    ): void {
        Mail::to($destination)
            ->queue(
                new VerificationCodeMail(
                    code: $code,
                    purpose: $purpose,
                )
            );
    }
}
