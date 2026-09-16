<?php

namespace App\Contracts;

interface VerificationCodeSender
{
    public function send(
        string $destination,
        string $code,
        string $purpose
    ): void;
}
