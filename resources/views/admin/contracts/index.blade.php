@extends('layouts.admin')

@section('title', 'Sözleşmeler')

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yönetim / 02</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">Sözleşmeler</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Yayımlanan değişmez PDF sürümleri ve ilişkilendirildikleri Cari Plus ürünleri.</p>
        </div>
        <a class="inline-flex min-h-12 items-center justify-center bg-slate-950 px-5 text-sm font-black text-white hover:bg-[#1746d1]" href="{{ route('admin.contracts.create') }}">Yeni sürüm yükle</a>
    </header>

    <div class="grid gap-5">
        @forelse ($contracts as $contract)
            <article class="border-2 border-slate-950 bg-white">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-slate-950 p-5 sm:p-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class(['px-2.5 py-1 text-xs font-black uppercase', 'bg-emerald-100 text-emerald-900' => $contract->is_active, 'bg-slate-200 text-slate-600' => ! $contract->is_active])>{{ $contract->is_active ? 'Aktif' : 'Pasif' }}</span>
                            <span class="text-xs text-slate-500">{{ $contract->versions_count }} sürüm</span>
                        </div>
                        <h2 class="mt-3 text-2xl font-black tracking-[-0.035em]">{{ $contract->name }}</h2>
                    </div>
                    <span class="font-mono text-xs text-slate-400">{{ $contract->uuid }}</span>
                </div>

                <div class="divide-y divide-slate-200">
                    @forelse ($contract->versions as $version)
                        <div class="grid gap-4 p-5 sm:grid-cols-[auto_1fr_auto] sm:items-center sm:p-6">
                            <div class="grid size-14 place-items-center border-2 border-slate-950 font-mono text-sm font-black">{{ $version->version }}</div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold">{{ $version->effective_at->format('d.m.Y H:i') }} itibarıyla geçerli</p>
                                <p class="mt-1 break-all font-mono text-[0.68rem] text-slate-500">SHA-256: {{ $version->source_document_hash }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($version->services as $service)
                                        <span class="bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ $service->name }} · #{{ $service->cari_plus_product_id }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <a class="inline-flex min-h-11 items-center justify-center border-2 border-slate-950 px-4 text-sm font-black hover:bg-slate-950 hover:text-white" href="{{ route('admin.contract-versions.download', $version) }}">Kaynak PDF</a>
                        </div>
                    @empty
                        <p class="p-5 text-sm text-slate-600">Bu sözleşmenin henüz yayımlanmış sürümü yok.</p>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="border-2 border-dashed border-slate-400 bg-white/40 p-8 text-center">
                <h2 class="text-xl font-black">Henüz sözleşme yok</h2>
                <p class="mt-2 text-sm text-slate-600">İlk PDF sürümünü yükleyerek başlayın.</p>
            </div>
        @endforelse
    </div>

    {{ $contracts->links() }}
@endsection
