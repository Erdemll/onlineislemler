<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Doğrulama Kodu</title>
</head>

<body>

    <h2>Güvenlik Doğrulaması</h2>

    <p>
        İşleminizi tamamlamak için doğrulama kodunuz:
    </p>

    <p style="
        font-size: 32px;
        font-weight: bold;
        letter-spacing: 8px;
    ">
        {{ $code }}
    </p>

    <p>
        Bu kod 5 dakika boyunca geçerlidir.
    </p>

    <p>
        Bu işlemi siz başlatmadıysanız
        bu e-postayı dikkate almayınız.
    </p>

</body>
</html>