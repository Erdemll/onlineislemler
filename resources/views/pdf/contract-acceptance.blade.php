<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px 42px; }
        body { color: #0f172a; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.45; }
        h1 { font-size: 21px; margin: 0 0 6px; }
        h2 { border-bottom: 1px solid #0f172a; font-size: 12px; margin: 20px 0 8px; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; }
        td { border-bottom: 1px solid #cbd5e1; padding: 6px 0; vertical-align: top; }
        td:first-child { color: #475569; width: 34%; }
        .badge { background: #1746d1; color: white; display: inline-block; font-size: 8px; padding: 4px 7px; }
        .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 8px; overflow-wrap: anywhere; }
        .signature { border: 1px solid #0f172a; height: 105px; margin-top: 8px; padding: 10px; text-align: center; }
        .signature img { max-height: 85px; max-width: 340px; }
        .notice { background: #f1f5f9; border-left: 4px solid #1746d1; margin-top: 20px; padding: 10px; }
    </style>
</head>
<body>
    <span class="badge">ELEKTRONİK KABUL KAYDI</span>
    <h1>Sözleşme kabul ve işlem bilgileri</h1>
    <p>Bu sayfa, aşağıda kimliği belirtilen müşterinin sözleşmeyi görüntüleyip çizilmiş imzası ve kayıtlı e-posta adresine gönderilen tek kullanımlık kod ile kabul ettiğini kayıt altına alır.</p>

    <h2>Sözleşme ve hizmet</h2>
    <table>
        <tr><td>Sözleşme</td><td>{{ $contract->name }}</td></tr>
        <tr><td>Sürüm</td><td>{{ $contractVersion->version }}</td></tr>
        <tr><td>Hizmet</td><td>{{ $order->service_name_snapshot }}</td></tr>
        <tr><td>Hizmet bedeli</td><td>{{ number_format((float) $order->unit_price, 2, ',', '.') }} {{ $order->currency }} (KDV dahil)</td></tr>
        <tr><td>Kaynak belge SHA-256</td><td class="hash">{{ $contractVersion->source_document_hash }}</td></tr>
    </table>

    <h2>Kabul eden</h2>
    <table>
        <tr><td>Müşteri numarası</td><td>{{ $customer->uuid }}</td></tr>
        <tr><td>Ad soyad / yetkili</td><td>{{ $customer->first_name }} {{ $customer->last_name }}</td></tr>
        @if ($customer->company_title)<tr><td>Firma ünvanı</td><td>{{ $customer->company_title }}</td></tr>@endif
        <tr><td>E-posta</td><td>{{ $customer->email }}</td></tr>
        <tr><td>Telefon</td><td>{{ $customer->phone }}</td></tr>
    </table>

    <h2>Doğrulama ve teknik kayıt</h2>
    <table>
        <tr><td>Kabul kayıt numarası</td><td>{{ $acceptanceUuid }}</td></tr>
        <tr><td>Kabul zamanı</td><td>{{ $acceptedAt->timezone('Europe/Istanbul')->format('d.m.Y H:i:s.u') }} Europe/Istanbul</td></tr>
        <tr><td>Yöntem</td><td>Kayıtlı e-posta adresine gönderilen tek kullanımlık kod</td></tr>
        <tr><td>OTP doğrulaması</td><td>Başarılı</td></tr>
        <tr><td>IP adresi</td><td>{{ $challenge->ip_address }}</td></tr>
        <tr><td>User-Agent</td><td>{{ $challenge->user_agent ?: 'Kaydedilmedi' }}</td></tr>
        <tr><td>İmza SHA-256</td><td class="hash">{{ $challenge->signature_hash }}</td></tr>
    </table>

    <h2>Çizilmiş imza</h2>
    <div class="signature"><img src="{{ $signatureData }}" alt="Müşteri imzası"></div>

    <div class="notice">Bu işlem, 5070 sayılı Kanun kapsamında nitelikli sertifikaya dayalı güvenli elektronik imza olarak değil; oturum, belge sürümü, çizilmiş imza, e-posta OTP doğrulaması ve teknik kayıtlarla desteklenen elektronik sözleşme kabulü olarak kaydedilmiştir.</div>
</body>
</html>
