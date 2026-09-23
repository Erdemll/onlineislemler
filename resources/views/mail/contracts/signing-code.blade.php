<x-mail::message>
<img src="{{ asset('logo.png') }}" width="180" alt="Tepenet Güvenlik">

# Sözleşme onay kodunuz

**{{ $contractName }}** sözleşmesini **{{ $serviceName }}** hizmeti için onaylamak üzeresiniz.

Onay kodunuz:

<div style="margin:20px 0;padding:18px;background:#0d3571;color:#ffffff;font-size:28px;font-weight:700;letter-spacing:4px;text-align:center;">{{ $code }}</div>

Bu kod 10 dakika boyunca geçerlidir. Bu işlemi siz başlatmadıysanız kodu kimseyle paylaşmayın ve işlemi tamamlamayın.

Saygılarımızla,<br>
{{ config('app.name') }}
</x-mail::message>
