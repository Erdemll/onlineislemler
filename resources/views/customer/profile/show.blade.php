@extends('layouts.customer')

@section('title', 'Bilgilerim')

@section('content')
    <div class="grid gap-8">
        <div class="grid gap-3 border-b-2 border-slate-950 pb-7">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">Hesap ayarları</p>
            <h1 class="text-4xl font-semibold tracking-[-0.05em]">Bilgilerim</h1>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">İletişim bilgilerini güncel tut. Telefon değişiklikleri SMS koduyla doğrulanır.</p>
        </div>

        <x-customer.status />

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="border-2 border-slate-950 bg-white">
                <div class="border-b-2 border-slate-950 p-6">
                    <span class="font-mono text-xs font-bold text-[#1746d1]">01 / KİŞİSEL BİLGİLER</span>
                    <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em]">Ad ve soyad</h2>
                </div>
                <form class="grid gap-5 p-6" method="POST" action="{{ route('customer.profile.update') }}">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="first_name" label="Ad" :value="old('first_name', $customer->first_name)" autocomplete="given-name" required />
                        <x-customer.input name="last_name" label="Soyad" :value="old('last_name', $customer->last_name)" autocomplete="family-name" required />
                    </div>
                    <div class="grid gap-2 border-t border-slate-200 pt-5">
                        <span class="text-xs font-semibold text-slate-500">E-posta adresi</span>
                        <span class="text-sm font-medium">{{ $customer->email }}</span>
                    </div>
                    <x-customer.submit class="sm:w-auto" label="Bilgileri güncelle" />
                </form>
            </section>

            <section class="border-2 border-slate-950 bg-white">
                <div class="border-b-2 border-slate-950 p-6">
                    <span class="font-mono text-xs font-bold text-[#1746d1]">02 / TELEFON</span>
                    <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em]">Numara değişikliği</h2>
                </div>
                <form class="grid gap-5 p-6" method="POST" action="{{ route('customer.phone.change.request') }}">
                    @csrf
                    <div class="grid gap-2 border-l-4 border-[#1746d1] bg-blue-50 px-4 py-3">
                        <span class="text-xs font-semibold text-blue-800">Kayıtlı telefon</span>
                        <span class="font-mono text-sm font-semibold text-blue-950">{{ $customer->phone }}</span>
                    </div>
                    <x-customer.input name="phone" label="Yeni telefon numarası" type="tel" hint="05xx xxx xx xx" :value="old('phone')" autocomplete="tel" inputmode="tel" required />
                    <x-customer.input name="current_password" label="Mevcut şifre" type="password" autocomplete="current-password" required />
                    <p class="text-xs leading-5 text-slate-500">Yeni numaraya bir doğrulama kodu göndereceğiz. Değişiklik kod onaylandıktan sonra tamamlanır.</p>
                    <x-customer.submit class="sm:w-auto" label="Doğrulama kodu gönder" />
                </form>
            </section>
        </div>
    </div>
@endsection
