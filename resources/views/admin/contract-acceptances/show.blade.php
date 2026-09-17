@extends('layouts.admin')

@section('title', 'İmzalı sözleşme kaydı')

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
            <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('admin.contract-acceptances.index') }}">← İmzalananlara dön</a>
            <p class="mt-4 text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Delil kaydı</p>
            <h1 class="mt-3 text-3xl font-black tracking-[-0.045em] sm:text-5xl">{{ $contractAcceptance->contract_name_snapshot }}</h1>
            <p class="mt-2 text-sm text-slate-600">Sürüm {{ $contractAcceptance->contract_version_snapshot }} · {{ $contractAcceptance->serviceOrder->service_name_snapshot }}</p>
        </div>
        <a class="inline-flex min-h-12 items-center justify-center bg-slate-950 px-5 text-sm font-black text-white hover:bg-[#1746d1]" href="{{ route('admin.contract-acceptances.download', $contractAcceptance) }}">İmzalı PDF’yi indir</a>
    </header>

    <section class="grid gap-5 lg:grid-cols-2">
        <article class="border-2 border-slate-950 bg-white">
            <div class="border-b-2 border-slate-950 p-5"><h2 class="text-xl font-black">Kabul bilgileri</h2></div>
            <dl class="grid gap-px bg-slate-200">
                @foreach ([
                    'Kayıt UUID' => $contractAcceptance->uuid,
                    'İmzalayan' => $contractAcceptance->signer_name_snapshot,
                    'Firma' => $contractAcceptance->company_title_snapshot ?: '—',
                    'E-posta' => $contractAcceptance->email_snapshot,
                    'Telefon' => $contractAcceptance->phone_snapshot,
                    'Kabul zamanı' => $contractAcceptance->accepted_at->format('d.m.Y H:i:s.u'),
                    'Doğrulama' => strtoupper($contractAcceptance->delivery_channel).' OTP',
                ] as $label => $value)
                    <div class="grid gap-1 bg-white p-4 sm:grid-cols-[9rem_1fr] sm:gap-4">
                        <dt class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $label }}</dt>
                        <dd class="break-all text-sm font-semibold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </article>

        <article class="border-2 border-slate-950 bg-white">
            <div class="border-b-2 border-slate-950 p-5"><h2 class="text-xl font-black">Teknik deliller</h2></div>
            <dl class="grid gap-px bg-slate-200">
                @foreach ([
                    'IP adresi' => $contractAcceptance->ip_address,
                    'User-Agent' => $contractAcceptance->user_agent ?: '—',
                    'Kaynak PDF hash' => $contractAcceptance->source_document_hash,
                    'İmza hash' => $contractAcceptance->signature_hash,
                    'İmzalı PDF hash' => $contractAcceptance->signed_document_hash,
                    'Oturum hash' => $contractAcceptance->session_identifier_hash,
                ] as $label => $value)
                    <div class="grid gap-1 bg-white p-4">
                        <dt class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $label }}</dt>
                        <dd class="break-all font-mono text-xs leading-5">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </article>
    </section>

    <section class="border-2 border-slate-950 bg-white">
        <div class="border-b-2 border-slate-950 p-5">
            <p class="text-xs font-black uppercase tracking-wider text-[#1746d1]">Hash zinciri</p>
            <h2 class="mt-2 text-2xl font-black">Audit olayları</h2>
        </div>
        <ol class="divide-y divide-slate-200">
            @forelse ($contractAcceptance->events as $event)
                <li class="grid gap-3 p-5 sm:grid-cols-[3rem_1fr_auto] sm:items-start">
                    <span class="grid size-10 place-items-center border-2 border-slate-950 font-mono text-xs font-black">{{ str_pad((string) $event->sequence, 2, '0', STR_PAD_LEFT) }}</span>
                    <div class="min-w-0">
                        <p class="font-black">{{ $event->event_type }}</p>
                        <p class="mt-1 break-all font-mono text-[0.68rem] leading-5 text-slate-500">{{ $event->event_hash }}</p>
                    </div>
                    <time class="text-xs font-bold text-slate-500">{{ $event->occurred_at->format('d.m.Y H:i:s.u') }}</time>
                </li>
            @empty
                <li class="p-5 text-sm text-slate-600">Bu kayıt için audit olayı bulunmuyor.</li>
            @endforelse
        </ol>
    </section>
@endsection
