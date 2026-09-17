<?php

namespace App\Services\Verification;

use App\Contracts\VerificationCodeSender;
use App\Exceptions\MailDeliveryException;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailVerificationCodeSender implements VerificationCodeSender
{
    public function send(
        string $destination,
        string $code,
        string $purpose
    ): void {
        try {
            Mail::to($destination)
                ->send(
                    new VerificationCodeMail(
                        code: $code,
                        purpose: $purpose,
                    )
                );
        } catch (Throwable $exception) {
            throw new MailDeliveryException(previous: $exception);
        }
    }
}
