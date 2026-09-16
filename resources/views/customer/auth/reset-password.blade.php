@extends('layouts.customer')

@section('title', 'Yeni şifre')

@section('content')
    <div class="mx-auto grid max-w-4xl overflow-hidden border-2 border-slate-950 bg-white md:grid-cols-[0.8fr_1.2fr]">
        <div class="border-b-2 border-slate-950 bg-slate-100 p-7 md:border-b-0 md:border-r-2 sm:p-9">
            <span class="text-6xl font-semibold tracking-[-0.08em] text-[#1746d1]">03</span>
            <h1 class="mt-8 text-3xl font-semibold tracking-[-0.04em]">Yeni şifreni belirle.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Daha önce kullanmadığın, en az 12 karakterden oluşan güçlü bir şifre seç.</p>
        </div>
        <div class="grid gap-7 p-7 sm:p-10">
            <div class="grid gap-2">
                <p class="text-sm font-semibold text-[#1746d1]">Son adım</p>
                <h2 class="text-2xl font-semibold tracking-[-0.03em]">Şifre oluştur</h2>
            </div>
            <form class="grid gap-5" method="POST" action="{{ route('customer.password.update') }}">
                @csrf
                <x-customer.input name="password" label="Yeni şifre" type="password" hint="En az 12 karakter" autocomplete="new-password" required autofocus />
                <x-customer.input name="password_confirmation" label="Yeni şifre tekrar" type="password" autocomplete="new-password" required />
                <x-customer.submit label="Şifreyi değiştir" />
            </form>
        </div>
    </div>
@endsection
