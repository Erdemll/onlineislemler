<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class MailDeliveryException extends Exception
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            'E-posta doğrulama kodu gönderilemedi.',
            previous: $previous,
        );
    }

    /** @return array{provider: string} */
    public function context(): array
    {
        return ['provider' => 'resend'];
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
                'email_delivery' => 'Doğrulama e-postası şu anda gönderilemiyor. Lütfen kısa süre sonra tekrar deneyin.',
            ]);
    }
}
