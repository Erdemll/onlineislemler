@extends('layouts.customer')

@section('title', 'Hizmetler ve Abonelikler')

@section('content')
    <section class="flex flex-col gap-8">
        <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
            <div class="max-w-2xl">
                <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">02 / Hizmet kataloğu</p>
                <h1 class="text-4xl font-black tracking-[-0.055em] sm:text-6xl">İhtiyacın kadar.<br>Hepsi bu.</h1>
            </div>
            <div class="flex max-w-sm flex-col gap-3">
                <p class="text-sm leading-6 text-slate-600">
                    Bir hizmet seçtiğinde önce sözleşme ve güvenli onay adımına geçersin. Fatura yalnızca sözleşme kabul edildikten sonra oluşturulur.
                </p>
                <form method="POST" action="{{ route('customer.services.sync') }}">
                    @csrf
                    <button
                        type="submit"
                        @disabled(! $canSync)
                        class="border border-slate-950 bg-white px-3 py-2 text-xs font-black uppercase tracking-wider transition hover:bg-slate-950 hover:text-white disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-100 disabled:text-slate-400"
                    >
                        Cari Plus’tan güncelle
                    </button>
                </form>
                <p class="text-xs text-slate-500">
                    @if ($lastSyncedAt)
                        Son güncelleme: {{ $lastSyncedAt->format('d.m.Y H:i') }}
                    @else
                        Katalog henüz Cari Plus’tan alınmadı.
                    @endif
                </p>
            </div>
        </header>

        @unless ($canPurchase)
            <div class="border-2 border-amber-700 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <strong>Satın alma henüz hazır değil.</strong>
                Cari Plus bağlantısı ve müşteri eşleşmesi tamamlandığında bu ekrandan doğrudan fatura oluşturabileceksin.
            </div>
        @endunless

        <div class="grid gap-5 md:grid-cols-2">
            @forelse ($services as $service)
                @php($hasContract = $service->contractVersions->isNotEmpty())
                <article class="group flex min-h-72 flex-col justify-between border-2 border-slate-950 bg-white p-6 transition hover:-translate-y-1 hover:shadow-[7px_7px_0_#0f172a] sm:p-7">
                    <div>
                        <div class="mb-8 flex items-start justify-between gap-4">
                            <span class="grid size-10 place-items-center border border-slate-950 text-xs font-black">
                                {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <span @class([
                                'px-3 py-1 text-xs font-black uppercase tracking-wider',
                                'bg-[#d7ff43]' => $hasContract,
                                'bg-slate-200 text-slate-600' => ! $hasContract,
                            ])>{{ $hasContract ? 'KDV dahil' : 'Sözleşme hazırlanıyor' }}</span>
                        </div>

                        <h2 class="text-2xl font-black tracking-[-0.04em]">{{ $service->name }}</h2>
                        <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">{{ $service->description }}</p>
                    </div>

                    <div class="mt-8 flex flex-col gap-4 border-t border-slate-300 pt-5 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="block text-xs font-bold uppercase tracking-wider text-slate-500">Hizmet bedeli</span>
                            <span class="text-3xl font-black tracking-[-0.05em]">{{ number_format($service->grossPrice(), 2, ',', '.') }} ₺</span>
                        </div>

                        <form method="POST" action="{{ route('customer.services.purchase', $service) }}">
                            @csrf
                            <button
                                type="submit"
                                @disabled(! $canPurchase || ! $hasContract)
                                class="w-full border-2 border-slate-950 bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-[#1746d1] disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 sm:w-auto"
                            >
                                {{ $hasContract ? 'Sözleşmeyi incele →' : 'Yakında' }}
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="border-2 border-dashed border-slate-400 p-8 text-sm text-slate-600 md:col-span-2">
                    Şu anda satın alınabilir bir hizmet bulunmuyor.
                </div>
            @endforelse
        </div>
    </section>
@endsection
