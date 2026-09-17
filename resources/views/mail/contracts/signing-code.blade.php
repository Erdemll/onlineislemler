<x-mail::message>
<img src="{{ asset('logo.png') }}" width="180" alt="Tepenet Güvenlik">

# Sözleşme onay kodunuz

**{{ $contractName }}** sözleşmesini **{{ $serviceName }}** hizmeti için onaylamak üzeresiniz.

Onay kodunuz:

# {{ $code }}

Bu kod 10 dakika boyunca geçerlidir. Bu işlemi siz başlatmadıysanız kodu kimseyle paylaşmayın ve işlemi tamamlamayın.

Saygılarımızla,<br>
{{ config('app.name') }}
</x-mail::message>
