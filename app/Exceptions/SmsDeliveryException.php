<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SmsDeliveryException extends Exception
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'SMS doğrulama kodu gönderilemedi.',
            0,
            $previous,
        );
    }

    /**
     * @return array{provider: string, reason: string, status: int|null}
     */
    public function context(): array
    {
        return [
            'provider' => 'verimor',
            'reason' => $this->reason,
            'status' => $this->status,
        ];
    }

    public function render(Request $request): RedirectResponse
    {
        return back()
            ->withInput($request->except([
                'password',
                'password_confirmation',
                'current_password',
            ]))
            ->withErrors([
                'sms' => 'Doğrulama kodu şu anda gönderilemiyor. Lütfen kısa süre sonra tekrar deneyin.',
            ]);
    }
}
