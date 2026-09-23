@extends('layouts.customer')

@section('title', 'Hesap özeti')

@section('content')
    <div class="grid gap-6">
        <section class="portal-welcome">
            <div class="relative z-10 grid gap-4">
                <span class="portal-eyebrow">Tepenet Güvenlik · Online İşlemler</span>
                <h1>Merhaba, {{ auth('customer')->user()->first_name }}.</h1>
                <p>Faturalarını, hizmetlerini, sözleşmelerini ve destek taleplerini tek yerden yönet.</p>
                <a class="portal-welcome-link" href="{{ route('customer.invoices.index') }}">Faturalarımı görüntüle <span aria-hidden="true">→</span></a>
            </div>
            <span class="portal-welcome-decoration" aria-hidden="true">T</span>
        </section>

        <section aria-label="Online işlem modülleri" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['icon' => '▤', 'title' => 'Faturalar', 'text' => 'Güncel ve geçmiş faturalarını görüntüle, ödeme durumunu takip et.', 'route' => 'customer.invoices.index'],
                ['icon' => '◇', 'title' => 'Hizmetler ve Abonelikler', 'text' => 'Kullanılabilir hizmetleri incele ve yeni hizmet talebi oluştur.', 'route' => 'customer.services.index'],
                ['icon' => '▣', 'title' => 'Sözleşmeler', 'text' => 'Bekleyen sözleşmeleri imzala, kabul edilen belgeleri indir.', 'route' => 'customer.contracts.index'],
                ['icon' => '◎', 'title' => 'Destek', 'text' => 'Destek taleplerini izle veya ekibimize yeni bir talep ilet.', 'route' => 'customer.support.index'],
                ['icon' => '♙', 'title' => 'Bilgilerim', 'text' => 'Hesap ve iletişim bilgilerini güvenle güncelle.', 'route' => 'customer.profile'],
            ] as $item)
                <a class="portal-module" href="{{ route($item['route']) }}">
                    <span class="portal-module-icon" aria-hidden="true">{{ $item['icon'] }}</span>
                    <span class="portal-module-body"><strong>{{ $item['title'] }}</strong><span>{{ $item['text'] }}</span></span>
                    <span class="portal-module-arrow" aria-hidden="true">→</span>
                </a>
            @endforeach
        </section>
    </div>
@endsection
