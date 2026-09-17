@extends('layouts.customer')

@section('title', 'Şifremi unuttum')

@section('content')
    <div class="mx-auto grid max-w-4xl overflow-hidden border-2 border-slate-950 bg-white md:grid-cols-[0.8fr_1.2fr]">
        <div class="border-b-2 border-slate-950 bg-slate-100 p-7 md:border-b-0 md:border-r-2 sm:p-9">
            <span class="text-6xl font-semibold tracking-[-0.08em] text-[#1746d1]">01</span>
            <h1 class="mt-8 text-3xl font-semibold tracking-[-0.04em]">Şifreni yenile.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Kayıtlı e-posta adresini yaz. Altı haneli doğrulama kodunu e-posta ile gönderelim.</p>
        </div>
        <div class="grid gap-7 p-7 sm:p-10">
            <div class="grid gap-2">
                <p class="text-sm font-semibold text-[#1746d1]">E-posta doğrulaması</p>
                <h2 class="text-2xl font-semibold tracking-[-0.03em]">Kayıtlı e-posta adresin</h2>
            </div>
            <x-customer.status />
            <form class="grid gap-5" method="POST" action="{{ route('customer.password.send') }}">
                @csrf
                <x-customer.input name="email" label="E-posta adresi" type="email" :value="old('email')" autocomplete="email" inputmode="email" required autofocus />
                <x-customer.submit label="E-posta kodu gönder" />
            </form>
            <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('customer.login') }}">Giriş ekranına dön</a>
        </div>
    </div>
@endsection
