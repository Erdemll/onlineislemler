<?php

namespace Tests\Feature\Customer;

use App\Mail\VerificationCodeMail;
use Tests\TestCase;

class VerificationCodeMailTest extends TestCase
{
    public function test_email_verification_mail_has_expected_subject_and_content(): void
    {
        $mail = new VerificationCodeMail(
            code: '123456',
            purpose: 'email_verification',
        );

        $mail->assertHasSubject('E-posta Doğrulama Kodunuz');
        $mail->assertSeeInHtml('123456');
        $mail->assertSeeInHtml('5 dakika');
        $mail->assertSeeInText('123456');
        $mail->assertSeeInText('5 dakika');
    }

    public function test_password_reset_mail_has_expected_subject(): void
    {
        $mail = new VerificationCodeMail(
            code: '654321',
            purpose: 'password_reset',
        );

        $mail->assertHasSubject('Şifre Sıfırlama Kodunuz');
    }

    public function test_phone_change_mail_has_expected_subject(): void
    {
        $mail = new VerificationCodeMail(
            code: '123456',
            purpose: 'phone_change',
        );

        $mail->assertHasSubject('Telefon Değişikliği Doğrulama Kodunuz');
    }
}
