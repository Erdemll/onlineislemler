<?php

use App\Mail\ContractSigningCodeMail;

it('explains the contract OTP without exposing unrelated account actions', function () {
    $mail = new ContractSigningCodeMail(
        code: '482913',
        contractName: 'Kamera Sistemleri Abonelik Sözleşmesi',
        serviceName: 'Kamera Sistemleri',
    );

    $mail->assertHasSubject('Sözleşme Onay Kodunuz');
    $mail->assertSeeInHtml('482913');
    $mail->assertSeeInHtml('Kamera Sistemleri Abonelik Sözleşmesi');
    $mail->assertSeeInText('10 dakika');
});
