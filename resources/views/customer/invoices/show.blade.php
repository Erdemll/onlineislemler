@extends('layouts.customer')

@section('title', 'Satış Faturası')

@section('content')
    <div class="grid gap-7">
        <header class="border-b-2 border-slate-950 pb-7">
            <a href="{{ route('customer.invoices.index') }}" class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-2 underline-offset-4">← Faturalara dön</a>
            <p class="mt-4 text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">Cari Plus / Satış faturası</p>
            <h1 class="mt-2 break-words text-3xl font-black tracking-[-0.045em] sm:text-5xl">{{ $details['number'] ?? $invoice->invoice_number ?? 'Fatura hazırlanıyor' }}</h1>
            @if ($details !== null && $details['title'] !== null)
                <p class="mt-3 text-sm text-slate-600">{{ $details['title'] }}</p>
            @endif
        </header>

        @if ($details === null && $invoice->cari_plus_invoice_id !== null)
            <p class="border-l-4 border-amber-600 bg-amber-50 px-4 py-3 text-sm text-amber-950">Cari Plus fatura ayrıntılarına şu anda ulaşılamıyor. Aşağıda kaydedilmiş fatura bilgileri gösteriliyor; daha sonra tekrar deneyebilirsiniz.</p>
        @endif

        <section class="grid gap-4 sm:grid-cols-3" aria-label="Fatura özeti">
            <div class="border-2 border-slate-950 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Fatura tarihi</p>
                <p class="mt-2 text-lg font-black">{{ $invoice->invoice_date?->format('d.m.Y') ?? '—' }}</p>
            </div>
            <div class="border-2 border-slate-950 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Son ödeme</p>
                <p class="mt-2 text-lg font-black">{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</p>
            </div>
            <div class="border-2 border-slate-950 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Portal ödeme durumu</p>
                <p class="mt-2 text-lg font-black">{{ $invoice->status->label() }}</p>
                @if ($details !== null)
                    <p class="mt-1 text-xs text-slate-600">Cari Plus: {{ match ($details['status']) { 'paid' => 'Ödendi', 'issued' => 'Kesildi', 'draft' => 'Taslak', 'cancelled' => 'İptal edildi', 'partial_refund' => 'Kısmi iade', 'refunded' => 'İade edildi' } }}</p>
                @endif
            </div>
        </section>

        @if ($details !== null)
            <section class="overflow-hidden border-2 border-slate-950 bg-white" aria-label="Fatura kalemleri">
                <div class="border-b-2 border-slate-950 bg-slate-950 px-5 py-4 text-sm font-black text-white">Cari Plus fatura kalemleri</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[580px] text-left text-sm">
                        <thead class="border-b border-slate-300 bg-slate-100 text-xs uppercase tracking-wider text-slate-600">
                            <tr>
                                <th scope="col" class="px-5 py-3">Kalem</th>
                                <th scope="col" class="px-5 py-3 text-right">Miktar</th>
                                <th scope="col" class="px-5 py-3 text-right">Net birim</th>
                                <th scope="col" class="px-5 py-3 text-right">KDV</th>
                                <th scope="col" class="px-5 py-3 text-right">Net tutar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($details['items'] as $item)
                                <tr>
                                    <td class="px-5 py-4 font-semibold">{{ $item['name'] }}</td>
                                    <td class="px-5 py-4 text-right">{{ number_format($item['quantity'], 2, ',', '.') }}</td>
                                    <td class="px-5 py-4 text-right">{{ number_format($item['unit_price'], 2, ',', '.') }} ₺</td>
                                    <td class="px-5 py-4 text-right">%{{ number_format($item['tax_rate'], 2, ',', '.') }}</td>
                                    <td class="px-5 py-4 text-right font-bold">{{ number_format($item['amount'], 2, ',', '.') }} ₺</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-6 text-slate-600">Bu faturada kalem bulunmuyor.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="grid gap-3 border-2 border-slate-950 bg-white p-5 sm:ml-auto sm:w-full sm:max-w-sm" aria-label="Fatura tutarları">
            <div class="flex items-center justify-between gap-4 text-sm"><span>Ara toplam</span><strong>{{ number_format($details['subtotal'] ?? (float) $invoice->subtotal, 2, ',', '.') }} ₺</strong></div>
            <div class="flex items-center justify-between gap-4 text-sm"><span>KDV</span><strong>{{ number_format($details['tax_amount'] ?? (float) $invoice->tax_amount, 2, ',', '.') }} ₺</strong></div>
            <div class="flex items-center justify-between gap-4 border-t border-slate-300 pt-3 text-lg"><span class="font-black">Genel toplam</span><strong>{{ number_format($details['total'] ?? (float) $invoice->total, 2, ',', '.') }} ₺</strong></div>
            @if ($details !== null)
                <div class="flex items-center justify-between gap-4 text-sm"><span>Cari Plus tahsilatı</span><strong>{{ number_format($details['paid_amount'], 2, ',', '.') }} ₺</strong></div>
                <div class="flex items-center justify-between gap-4 text-sm"><span>Kalan</span><strong>{{ number_format(max(0, $details['total'] - $details['paid_amount']), 2, ',', '.') }} ₺</strong></div>
            @endif
        </section>
    </div>
@endsection
