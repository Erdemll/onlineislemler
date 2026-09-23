@extends('layouts.admin')

@section('title', 'Müşteriler')

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yönetim / 05</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">Müşteriler</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Müşteri hesap bilgilerini görüntüleyin ve limit tanımlayın.</p>
        </div>
    </header>

    <section class="border-2 border-slate-950 bg-white">
        <div class="hidden grid-cols-[1fr_0.6fr_1fr_1fr_0.7fr_auto] gap-4 border-b-2 border-slate-950 bg-slate-950 px-5 py-3 text-xs font-black uppercase tracking-wider text-white lg:grid">
            <span>Müşteri</span>
            <span>Tür</span>
            <span>E-posta</span>
            <span>Telefon</span>
            <span>Limit</span>
            <span>İşlem</span>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse ($customers as $customer)
                <article class="grid gap-4 p-5 lg:grid-cols-[1fr_0.6fr_1fr_1fr_0.7fr_auto] lg:items-center">
                    <div class="min-w-0">
                        <p class="text-sm font-bold">{{ $customer->billingTitle() }}</p>
                    </div>
                    <div>
                        <p class="text-sm">{{ $customer->account_type->value === 'individual' ? 'Bireysel' : 'Kurumsal' }}</p>
                    </div>
                    <div>
                        <p class="text-sm break-all">{{ $customer->email }}</p>
                    </div>
                    <div>
                        <p class="text-sm break-all">{{ $customer->mobile_phone ?? $customer->phone }}</p>
                    </div>
                    <div>
                        <p class="text-sm">{{ number_format($customer->credit_limit / 100, 2, ',', '.') }} ₺</p>
                    </div>
                    <a class="inline-flex min-h-11 items-center justify-center border-2 border-slate-950 px-4 text-sm font-black hover:bg-slate-950 hover:text-white" href="{{ route('admin.customers.edit', ['customer' => $customer->uuid]) }}">Düzenle</a>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-slate-600">Henüz kayıtlı müşteri bulunamadı.</p>
            @endforelse
        </div>
    </section>

    {{ $customers->links() }}
@endsection
