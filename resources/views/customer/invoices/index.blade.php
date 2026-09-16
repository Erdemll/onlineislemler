@extends('layouts.customer')

@section('title', 'Faturalar')

@section('content')
    <section class="flex flex-col gap-8">
        <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
            <div>
                <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">01 / Hesap hareketleri</p>
                <h1 class="text-4xl font-black tracking-[-0.055em] sm:text-6xl">Faturalar</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600">Cari Plus’taki güncel faturaların ve ödeme durumların tek yerde.</p>
            </div>

            <form method="POST" action="{{ route('customer.invoices.sync') }}">
                @csrf
                <button
                    type="submit"
                    @disabled(! $canSync)
                    class="border-2 border-slate-950 bg-white px-5 py-3 text-sm font-black transition hover:bg-slate-950 hover:text-white disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                >
                    Cari Plus’tan yenile
                </button>
            </form>
        </header>

        @unless ($canSync)
            <p class="border-l-4 border-amber-600 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Cari Plus müşteri eşleşmesi tamamlanmadığı için uzaktaki faturalar henüz yenilenemiyor.
            </p>
        @endunless

        @if ($invoices->isEmpty())
            <div class="grid min-h-72 place-items-center border-2 border-dashed border-slate-400 bg-white/40 p-8 text-center">
                <div>
                    <span class="mx-auto grid size-12 place-items-center border-2 border-slate-950 text-lg font-black">0</span>
                    <h2 class="mt-5 text-xl font-black">Henüz fatura yok</h2>
                    <p class="mt-2 text-sm text-slate-600">Yeni bir hizmet seçtiğinde faturası burada görünecek.</p>
                    <a href="{{ route('customer.services.index') }}" class="mt-5 inline-block bg-[#1746d1] px-5 py-3 text-sm font-black text-white hover:bg-slate-950">Hizmetlere git →</a>
                </div>
            </div>
        @else
            <div class="overflow-hidden border-2 border-slate-950 bg-white">
                <div class="hidden grid-cols-[1.25fr_1fr_1fr_1fr_auto] gap-4 border-b-2 border-slate-950 bg-slate-950 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white md:grid">
                    <span>Fatura</span>
                    <span>Tarih</span>
                    <span>Son ödeme</span>
                    <span>Tutar</span>
                    <span>Durum</span>
                </div>

                <div class="divide-y divide-slate-300">
                    @foreach ($invoices as $invoice)
                        @php
                            $statusClass = match ($invoice->status) {
                                \App\Enums\InvoiceStatus::Paid => 'bg-emerald-100 text-emerald-900',
                                \App\Enums\InvoiceStatus::Unpaid => 'bg-amber-200 text-amber-950',
                                \App\Enums\InvoiceStatus::Failed => 'bg-red-100 text-red-900',
                                \App\Enums\InvoiceStatus::Cancelled,
                                \App\Enums\InvoiceStatus::Refunded => 'bg-slate-200 text-slate-700',
                                default => 'bg-blue-100 text-blue-900',
                            };
                        @endphp

                        <article class="grid gap-4 px-5 py-5 md:grid-cols-[1.25fr_1fr_1fr_1fr_auto] md:items-center">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 md:hidden">Fatura</span>
                                <p class="font-black">{{ $invoice->invoice_number ?? 'Hazırlanıyor' }}</p>
                                <p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $invoice->items->first()?->name ?? 'Cari Plus faturası' }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 md:hidden">Tarih</span>
                                <p class="text-sm font-semibold">{{ $invoice->invoice_date?->format('d.m.Y') ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 md:hidden">Son ödeme</span>
                                <p class="text-sm font-semibold">{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 md:hidden">Tutar</span>
                                <p class="text-lg font-black">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency === 'TRY' ? '₺' : $invoice->currency }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                                <span class="px-3 py-1 text-xs font-black {{ $statusClass }}">{{ $invoice->status->label() }}</span>

                                @if (str_starts_with($invoice->idempotency_key, 'portal-') && in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::Failed], true))
                                    <form method="POST" action="{{ route('customer.invoices.retry', $invoice->uuid) }}">
                                        @csrf
                                        <button type="submit" class="border border-slate-950 px-3 py-1 text-xs font-black hover:bg-slate-950 hover:text-white">Tekrar dene</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            {{ $invoices->links() }}
        @endif
    </section>
@endsection
