<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tepenet Güvenlik doğrulama kodu</title>
</head>
<body style="margin:0;background:#f4f6fa;color:#172033;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6fa;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;border:1px solid #e2e7f0;border-radius:14px;background:#ffffff;">
                    <tr>
                        <td style="padding:24px;border-bottom:1px solid #e2e7f0;">
                            <img src="{{ asset('logo.png') }}" width="180" alt="Tepenet Güvenlik" style="display:block;width:180px;max-width:100%;height:auto;">
                            <p style="margin:12px 0 0;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#164c9c;">Online İşlemler</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <h1 style="margin:0;font-size:26px;line-height:1.2;">Güvenlik doğrulaması</h1>
                            <p style="margin:16px 0 0;font-size:15px;line-height:1.6;color:#475569;">İşleminizi tamamlamak için aşağıdaki altı haneli kodu kullanın.</p>
                            <p style="margin:24px 0;padding:18px;border-radius:10px;background:#0d3571;color:#ffffff;font-family:monospace;font-size:28px;font-weight:700;letter-spacing:4px;text-align:center;">{{ $code }}</p>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;">Bu kod 5 dakika boyunca geçerlidir. İşlemi siz başlatmadıysanız kodu paylaşmayın ve bu e-postayı dikkate almayın.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;background:#0d3571;color:#dce8ff;font-size:12px;">Tepenet Güvenlik · Güvenli müşteri işlemleri</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
