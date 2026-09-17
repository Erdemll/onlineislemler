@extends('layouts.customer')

@section('title', 'Sözleşmeler')

@section('content')
    <section class="flex flex-col gap-8">
        <header class="grid gap-4 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
            <div>
                <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">04 / Belge merkezi</p>
                <h1 class="text-4xl font-black tracking-[-0.055em] sm:text-6xl">Sözleşmeler.</h1>
            </div>
            <p class="max-w-md text-sm leading-6 text-slate-600">Bekleyen sözleşmeleri tamamla; kabul edilmiş belgelerin değişmez PDF kopyalarını ve işlem bilgilerini görüntüle.</p>
        </header>

        <div class="grid gap-4">
            @forelse ($orders as $order)
                <article class="grid gap-5 border-2 border-slate-950 bg-white p-5 sm:p-6 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="grid gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'px-2.5 py-1 text-xs font-black uppercase tracking-wider',
                                'bg-emerald-100 text-emerald-900' => $order->acceptance,
                                'bg-amber-100 text-amber-950' => ! $order->acceptance,
                            ])>{{ $order->acceptance ? 'Kabul edildi' : 'İmza bekliyor' }}</span>
                            <span class="text-xs text-slate-500">Talep {{ $order->uuid }}</span>
                        </div>
                        <h2 class="text-xl font-black tracking-[-0.03em]">{{ $order->contractVersion->contract->name }}</h2>
                        <p class="text-sm text-slate-600">{{ $order->service_name_snapshot }} · Sürüm {{ $order->contractVersion->version }}</p>
                        @if ($order->acceptance)
                            <p class="text-xs text-slate-500">Kabul zamanı: {{ $order->acceptance->accepted_at->format('d.m.Y H:i:s') }}</p>
                        @endif
                        @if ($order->last_error)
                            <p class="text-sm font-semibold text-red-800">{{ $order->last_error }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($order->acceptance)
                            <a class="border-2 border-slate-950 bg-slate-950 px-4 py-2.5 text-sm font-black text-white hover:bg-[#1746d1]" href="{{ route('customer.contracts.download', $order->acceptance) }}">İmzalı PDF’yi indir</a>
                        @else
                            <a class="border-2 border-slate-950 bg-[#d7ff43] px-4 py-2.5 text-sm font-black hover:bg-slate-950 hover:text-white" href="{{ route('customer.service-orders.contract.show', $order) }}">İncele ve imzala</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="border-2 border-dashed border-slate-400 p-8 text-sm text-slate-600">Henüz bir sözleşme veya hizmet talebiniz bulunmuyor.</div>
            @endforelse
        </div>
    </section>
@endsection
