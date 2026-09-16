@extends('layouts.customer')

@section('title', 'Hesap özeti')

@section('content')
    <div class="grid gap-8">
        <section class="grid gap-6 border-b-2 border-slate-950 pb-8 md:grid-cols-[1fr_auto] md:items-end">
            <div class="grid gap-3">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">Hesap özeti</p>
                <h1 class="text-4xl font-semibold tracking-[-0.05em] sm:text-5xl">Merhaba, {{ auth('customer')->user()->first_name }}.</h1>
                <p class="max-w-2xl text-sm leading-6 text-slate-600">Faturaların, hizmetlerin ve destek taleplerin bu ekranda toplanacak.</p>
            </div>
            <a class="inline-flex min-h-11 items-center justify-center border-2 border-slate-950 bg-white px-5 text-sm font-bold hover:bg-slate-950 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1746d1]" href="{{ route('customer.profile') }}">Bilgilerim</a>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Online işlem modülleri">
            @foreach ([
                ['no' => '01', 'title' => 'Faturalar', 'text' => 'Güncel ve geçmiş faturalarını görüntüle.', 'route' => 'customer.invoices.index'],
                ['no' => '02', 'title' => 'Hizmetler ve Abonelikler', 'text' => 'Aktif hizmet ve aboneliklerini yönet.', 'route' => 'customer.services.index'],
                ['no' => '03', 'title' => 'Destek', 'text' => 'Yeni talep oluştur ve mevcut taleplerini izle.', 'route' => 'customer.support.index'],
                ['no' => '04', 'title' => 'Sözleşmeler', 'text' => 'Sözleşmelerini görüntüle ve imza süreçlerini tamamla.', 'route' => 'customer.contracts.index'],
                ['no' => '05', 'title' => 'Bilgilerim', 'text' => 'İletişim ve hesap bilgilerini güncelle.', 'route' => 'customer.profile'],
            ] as $item)
                <a class="group flex min-h-48 flex-col justify-between gap-8 border-2 border-slate-950 bg-white p-6 transition hover:-translate-y-1 hover:bg-slate-950 hover:text-white focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#1746d1]" href="{{ route($item['route']) }}">
                    <div class="flex items-start justify-between gap-4">
                        <span class="font-mono text-sm font-bold text-[#1746d1] group-hover:text-blue-300">{{ $item['no'] }}</span>
                        <span class="font-mono text-lg" aria-hidden="true">↗</span>
                    </div>
                    <div class="grid gap-2">
                        <h2 class="text-xl font-semibold tracking-[-0.025em]">{{ $item['title'] }}</h2>
                        <p class="text-sm leading-6 text-slate-600 group-hover:text-slate-300">{{ $item['text'] }}</p>
                    </div>
                </a>
            @endforeach
        </section>
    </div>
@endsection
