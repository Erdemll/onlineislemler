@extends('layouts.customer')

@section('title', 'E-posta doğrulama')

@section('content')
    <div class="mx-auto max-w-3xl border-2 border-slate-950 bg-white">
        <div class="grid border-b-2 border-slate-950 sm:grid-cols-[8rem_1fr]">
            <div class="grid min-h-28 place-items-center bg-slate-950 text-3xl font-semibold text-white">@</div>
            <div class="grid gap-2 p-7 sm:p-9">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">E-posta kontrolü</p>
                <h1 class="text-3xl font-semibold tracking-[-0.04em]">E-posta adresinizi doğrulayın</h1>
                <p class="text-sm leading-6 text-slate-600">E-posta adresine gönderdiğimiz altı haneli kodu gir.</p>
            </div>
        </div>

        <div class="grid gap-6 p-7 sm:p-9">
            <x-customer.status />
            <form class="grid gap-5" method="POST" action="{{ route('customer.email.verify.store') }}">
                @csrf
                <x-customer.input class="text-center font-mono text-2xl font-semibold tracking-[0.35em]" name="code" label="Doğrulama kodu" type="text" hint="6 hane" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus />
                <x-customer.submit label="E-postayı doğrula" />
            </form>

            <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">Kod ulaşmadı mı?</p>
                <form method="POST" action="{{ route('customer.email.resend') }}">
                    @csrf
                    <button class="text-left text-sm font-bold text-[#1746d1] underline decoration-2 underline-offset-4" type="submit">Yeni kod gönder</button>
                </form>
            </div>
        </div>
    </div>
@endsection
