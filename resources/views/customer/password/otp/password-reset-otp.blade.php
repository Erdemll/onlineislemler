@extends('layouts.customer')

@section('title', 'Şifre doğrulama kodu')

@section('content')
    <div class="mx-auto grid max-w-4xl overflow-hidden border-2 border-slate-950 bg-white md:grid-cols-[0.8fr_1.2fr]">
        <div class="border-b-2 border-slate-950 bg-slate-100 p-7 md:border-b-0 md:border-r-2 sm:p-9">
            <span class="text-6xl font-semibold tracking-[-0.08em] text-[#1746d1]">02</span>
            <h1 class="mt-8 text-3xl font-semibold tracking-[-0.04em]">Kodu doğrula.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Telefonuna gelen altı haneli kodu gir. Kod beş dakika boyunca geçerlidir.</p>
        </div>
        <div class="grid gap-7 p-7 sm:p-10">
            <div class="grid gap-2">
                <p class="text-sm font-semibold text-[#1746d1]">Şifre sıfırlama</p>
                <h2 class="text-2xl font-semibold tracking-[-0.03em]">SMS doğrulama kodu</h2>
            </div>
            <x-customer.status />
            <form class="grid gap-5" method="POST" action="{{ route('customer.password.otp.verify') }}">
                @csrf
                <x-customer.input class="text-center font-mono text-2xl font-semibold tracking-[0.35em]" name="code" label="Doğrulama kodu" type="text" hint="6 hane" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus />
                <x-customer.submit label="Kodu doğrula" />
            </form>
            <a class="text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('customer.password.request') }}">Telefon numarasını değiştir</a>
        </div>
    </div>
@endsection
