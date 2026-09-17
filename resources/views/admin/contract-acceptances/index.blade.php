@extends('layouts.admin')

@section('title', 'İmzalanan sözleşmeler')

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yönetim / 03</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">İmzalananlar</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Kabul edilen sözleşmeler, doğrulama kayıtları ve değişmez PDF kopyaları.</p>
        </div>
        <form class="flex w-full max-w-md gap-2" method="GET" action="{{ route('admin.contract-acceptances.index') }}">
            <label class="sr-only" for="q">Sözleşme ara</label>
            <input class="min-h-12 min-w-0 flex-1 border-2 border-slate-950 bg-white px-3.5 text-base outline-none focus:border-[#1746d1]" id="q" name="q" value="{{ $search }}" placeholder="Ad, e-posta veya UUID">
            <button class="min-h-12 bg-slate-950 px-4 text-sm font-black text-white" type="submit">Ara</button>
        </form>
    </header>

    <div class="border-2 border-slate-950 bg-white">
        <div class="hidden grid-cols-[1.2fr_1fr_1fr_auto] gap-4 border-b-2 border-slate-950 bg-slate-950 px-5 py-3 text-xs font-black uppercase tracking-wider text-white md:grid">
            <span>Sözleşme</span><span>İmzalayan</span><span>Zaman</span><span>İşlem</span>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse ($acceptances as $acceptance)
                <article class="grid gap-4 p-5 md:grid-cols-[1.2fr_1fr_1fr_auto] md:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-slate-500 md:hidden">Sözleşme</p>
                        <p class="font-black">{{ $acceptance->contract_name_snapshot }}</p>
                        <p class="mt-1 text-xs text-slate-500">Sürüm {{ $acceptance->contract_version_snapshot }} · {{ $acceptance->serviceOrder->service_name_snapshot }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-slate-500 md:hidden">İmzalayan</p>
                        <p class="text-sm font-bold">{{ $acceptance->signer_name_snapshot }}</p>
                        <p class="mt-1 break-all text-xs text-slate-500">{{ $acceptance->email_snapshot }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-slate-500 md:hidden">Zaman</p>
                        <time class="text-sm font-bold">{{ $acceptance->accepted_at->format('d.m.Y H:i:s') }}</time>
                    </div>
                    <a class="inline-flex min-h-11 items-center justify-center border-2 border-slate-950 px-4 text-sm font-black hover:bg-slate-950 hover:text-white" href="{{ route('admin.contract-acceptances.show', $acceptance) }}">İncele</a>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-slate-600">{{ $search !== '' ? 'Aramayla eşleşen kayıt bulunamadı.' : 'Henüz imzalanmış sözleşme bulunmuyor.' }}</p>
            @endforelse
        </div>
    </div>

    {{ $acceptances->links() }}
@endsection
