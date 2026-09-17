@extends('layouts.admin')

@section('title', 'Genel bakış')

@section('content')
    <header class="grid gap-4 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yönetim / 01</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">Genel bakış</h1>
        </div>
        <a class="inline-flex min-h-12 items-center justify-center bg-slate-950 px-5 text-sm font-black text-white hover:bg-[#1746d1]" href="{{ route('admin.contracts.create') }}">Yeni sözleşme sürümü</a>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Yönetim özeti">
        @foreach ([
            ['label' => 'Sözleşme', 'value' => $contractCount],
            ['label' => 'Yayımlanan sürüm', 'value' => $versionCount],
            ['label' => 'İmzalanan belge', 'value' => $acceptanceCount],
            ['label' => 'Cari Plus ürünü', 'value' => $productCount],
            ['label' => 'Yanıt bekleyen destek', 'value' => $supportTicketCount],
        ] as $item)
            <article class="border-2 border-slate-950 bg-white p-5">
                <p class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $item['label'] }}</p>
                <p class="mt-5 text-4xl font-black tracking-[-0.06em]">{{ $item['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="border-2 border-slate-950 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b-2 border-slate-950 p-5">
            <div>
                <p class="text-xs font-black uppercase tracking-wider text-[#1746d1]">Son işlemler</p>
                <h2 class="mt-1 text-2xl font-black">İmzalanan sözleşmeler</h2>
            </div>
            <a class="inline-flex min-h-11 items-center font-bold underline decoration-2 underline-offset-4" href="{{ route('admin.contract-acceptances.index') }}">Tümünü gör →</a>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse ($recentAcceptances as $acceptance)
                <a class="grid gap-2 p-5 hover:bg-slate-50 sm:grid-cols-[1fr_auto] sm:items-center" href="{{ route('admin.contract-acceptances.show', $acceptance) }}">
                    <div>
                        <p class="font-black">{{ $acceptance->contract_name_snapshot }} · {{ $acceptance->contract_version_snapshot }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $acceptance->signer_name_snapshot }} · {{ $acceptance->serviceOrder->service_name_snapshot }}</p>
                    </div>
                    <time class="text-xs font-bold text-slate-500">{{ $acceptance->accepted_at->format('d.m.Y H:i') }}</time>
                </a>
            @empty
                <p class="p-6 text-sm text-slate-600">Henüz imzalanmış bir sözleşme bulunmuyor.</p>
            @endforelse
        </div>
    </section>
@endsection
