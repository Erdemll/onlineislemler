<?php

namespace Tests\Fakes;

use App\Contracts\SmsSender;

class FakeSmsSender implements SmsSender
{
    public array $sent = [];

    public function sendOtp(string $phone, string $code): void
    {
        $this->sent[] = [
            'phone' => $phone,
            'code' => $code,
        ];
    }

    public function lastCode(): ?string
    {
        return $this->sent[array_key_last($this->sent)]['code']
            ?? null;
    }
}