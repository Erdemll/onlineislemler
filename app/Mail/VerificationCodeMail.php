<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $code,
        public string $purpose,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectForPurpose(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            text: 'emails.verification-code-text',
        );
    }

    private function subjectForPurpose(): string
    {
        return match ($this->purpose) {
            'email_verification' => 'E-posta Doğrulama Kodunuz',

            'password_reset' => 'Şifre Sıfırlama Kodunuz',

            'phone_change' => 'Telefon Değişikliği Doğrulama Kodunuz',

            default => 'Güvenlik Doğrulama Kodunuz',
        };
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
