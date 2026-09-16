@extends('layouts.customer')

@section('title', 'Giriş yap')

@section('content')
    <div class="grid overflow-hidden border-2 border-slate-950 bg-white lg:grid-cols-[0.8fr_1.2fr]">
        <div class="flex flex-col justify-between gap-12 bg-slate-950 p-7 text-white sm:p-10">
            <div class="grid gap-5">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-300">Müşteri girişi</p>
                <h1 class="max-w-md text-4xl font-semibold leading-[1.05] tracking-[-0.045em] sm:text-5xl">Hesabına dön.</h1>
                <p class="max-w-sm text-sm leading-6 text-slate-300">Fatura, abonelik ve destek işlemlerine tek bir güvenli oturumdan ulaş.</p>
            </div>
            <div class="border-t border-slate-700 pt-5 text-xs leading-5 text-slate-400">Şifreni kimseyle paylaşma. Doğrulama kodları yalnızca kayıtlı telefonuna gönderilir.</div>
        </div>

        <div class="p-7 sm:p-10 lg:p-14">
            <div class="mx-auto grid max-w-md gap-8">
                <div class="grid gap-2">
                    <p class="text-sm font-semibold text-[#1746d1]">Tekrar hoş geldin</p>
                    <h2 class="text-2xl font-semibold tracking-[-0.03em]">Bilgilerinle giriş yap</h2>
                </div>

                <x-customer.status />

                <form class="grid gap-5" method="POST" action="{{ route('customer.login.store') }}">
                    @csrf

                    <x-customer.input name="email" label="E-posta adresi" type="email" :value="old('email')" autocomplete="email" inputmode="email" required autofocus />

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <label class="text-sm font-semibold" for="password">Şifre</label>
                            <a class="text-xs font-semibold text-[#1746d1] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1746d1]" href="{{ route('customer.password.request') }}">Şifremi unuttum</a>
                        </div>
                        <input class="min-h-12 w-full border-2 border-slate-950 bg-white px-3.5 py-2.5 text-base outline-none transition focus:border-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/20" id="password" type="password" name="password" autocomplete="current-password" required>
                        @error('password')
                            <p class="text-sm font-medium text-red-800">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-700">
                        <input class="size-4 rounded-none border-2 border-slate-950 accent-[#1746d1]" type="checkbox" name="remember" value="1">
                        Bu cihazda beni hatırla
                    </label>

                    <x-customer.submit label="Giriş yap" />
                </form>

                <p class="border-t border-slate-200 pt-5 text-sm text-slate-600">Henüz hesabın yok mu? <a class="font-bold text-slate-950 underline decoration-2 underline-offset-4" href="{{ route('customer.register') }}">Hesap oluştur</a></p>
            </div>
        </div>
    </div>
@endsection
