@extends('layouts.customer')

@section('title', 'Hesap oluştur')

@section('content')
    <div class="grid overflow-hidden border-2 border-slate-950 bg-white lg:grid-cols-[0.7fr_1.3fr]">
        <div class="flex flex-col justify-between gap-12 bg-[#1746d1] p-7 text-white sm:p-10">
            <div class="grid gap-5">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-100">Yeni müşteri</p>
                <h1 class="max-w-sm text-4xl font-semibold leading-[1.05] tracking-[-0.045em] sm:text-5xl">İşlemlerin tek yerde.</h1>
                <p class="max-w-sm text-sm leading-6 text-blue-100">Hesabını oluştur, telefonunu doğrula ve online işlemlere güvenle başla.</p>
            </div>
            <ol class="grid gap-3 border-t border-blue-300/50 pt-5 text-sm" aria-label="Kayıt adımları">
                <li class="flex gap-3"><span class="font-bold">01</span><span>Bilgilerini gir</span></li>
                <li class="flex gap-3 text-blue-100"><span class="font-bold">02</span><span>SMS kodunu doğrula</span></li>
                <li class="flex gap-3 text-blue-100"><span class="font-bold">03</span><span>Hesabını kullan</span></li>
            </ol>
        </div>

        <div class="p-7 sm:p-10 lg:p-14">
            <div class="mx-auto grid max-w-2xl gap-8">
                <div class="grid gap-2">
                    <p class="text-sm font-semibold text-[#1746d1]">Hesap oluştur</p>
                    <h2 class="text-2xl font-semibold tracking-[-0.03em]">Müşteri bilgileri</h2>
                    <p class="text-sm leading-6 text-slate-600">Tüm alanları eksiksiz doldur. Telefon numaran doğrulama için kullanılacak.</p>
                </div>

                <form class="grid gap-5" method="POST" action="{{ route('customer.register.store') }}">
                    @csrf
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="first_name" label="Ad" :value="old('first_name')" autocomplete="given-name" required autofocus />
                        <x-customer.input name="last_name" label="Soyad" :value="old('last_name')" autocomplete="family-name" required />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="phone" label="Telefon" type="tel" hint="05xx xxx xx xx" :value="old('phone')" autocomplete="tel" inputmode="tel" required />
                        <x-customer.input name="email" label="E-posta" type="email" :value="old('email')" autocomplete="email" inputmode="email" required />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="password" label="Şifre" type="password" hint="En az 12 karakter" autocomplete="new-password" required />
                        <x-customer.input name="password_confirmation" label="Şifre tekrar" type="password" autocomplete="new-password" required />
                    </div>
                    <x-customer.submit class="sm:w-auto sm:min-w-44" label="Hesap oluştur" />
                </form>

                <p class="border-t border-slate-200 pt-5 text-sm text-slate-600">Zaten hesabın var mı? <a class="font-bold text-slate-950 underline decoration-2 underline-offset-4" href="{{ route('customer.login') }}">Giriş yap</a></p>
            </div>
        </div>
    </div>
@endsection
