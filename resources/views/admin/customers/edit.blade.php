@extends('layouts.admin')

@section('title', 'Müşteri Düzenle')

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yönetim / 05</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">Müşteri Düzenle</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Kimlik bilgilerini inceleyin ve kredi limiti atayın.</p>
        </div>
    </header>

    <section class="grid gap-8">
        <form action="{{ route('admin.customers.update', $customer) }}" method="POST" class="border-2 border-slate-950 bg-white p-6">
            @csrf
            @method('PATCH')
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-black uppercase text-slate-700">Ad Soyad</label>
                    <p class="mt-1 text-sm">{{ $customer->billingTitle() }}</p>
                </div>
                <div>
                    <label class="block text-sm font-black uppercase text-slate-700">Tür</label>
                    <p class="mt-1 text-sm">{{ $customer->account_type->value === 'individual' ? 'Bireysel' : 'Kurumsal' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-black uppercase text-slate-700">E-posta</label>
                    <p class="mt-1 text-sm">{{ $customer->email }}</p>
                </div>
                <div>
                    <label class="block text-sm font-black uppercase text-slate-700">Telefon</label>
                    <p class="mt-1 text-sm">{{ $customer->mobile_phone ?? $customer->phone }}</p>
                </div>
                <div>
                    <label class="block text-sm font-black uppercase text-slate-700">TCKN / Vergi No</label>
                    <p class="mt-1 text-sm">{{ $customer->masked_national_id ?? $customer->masked_tax_number }}</p>
                </div>
            </div>
            <div class="mt-6">
                <label for="credit_limit" class="block text-sm font-black uppercase text-slate-700">Kredi Limiti (TL)</label>
                <input id="credit_limit" name="credit_limit" type="number" step="0.01" min="0" value="{{ old('credit_limit', $customer->credit_limit / 100) }}" class="mt-1 w-full border-2 border-slate-950 px-3.5 py-2 text-base outline-none focus:border-[#1746d1]" required>
                @error('credit_limit')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="mt-6">
                <button type="submit" class="inline-flex min-h-12 items-center justify-center bg-slate-950 px-5 text-sm font-black text-white hover:bg-[#1746d1]">Kaydet</button>
            </div>
        </form>
    </section>
@endsection
