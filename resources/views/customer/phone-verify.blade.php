@extends('layouts.customer')

@section('title', 'Telefon doğrulama')

@section('content')
    <div class="mx-auto max-w-3xl border-2 border-slate-950 bg-white">
        <div class="grid border-b-2 border-slate-950 sm:grid-cols-[8rem_1fr]">
            <div class="grid min-h-28 place-items-center bg-[#1746d1] text-5xl font-semibold tracking-[-0.08em] text-white">02</div>
            <div class="grid gap-2 p-7 sm:p-9">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">SMS doğrulama</p>
                <h1 class="text-3xl font-semibold tracking-[-0.04em]">Telefonunu doğrula</h1>
                <p class="text-sm leading-6 text-slate-600">Kayıtlı telefonuna gönderdiğimiz altı haneli kodu gir.</p>
            </div>
        </div>

        <div class="grid gap-6 p-7 sm:p-9">
            <x-customer.status />

            <form class="grid gap-5" method="POST" action="{{ route('customer.phone.verify.store') }}">
                @csrf
                <x-customer.input
                    class="text-center font-mono text-2xl font-semibold tracking-[0.35em]"
                    name="code"
                    label="Doğrulama kodu"
                    type="text"
                    hint="6 hane"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    autofocus
                />
                <x-customer.submit label="Telefonu doğrula" />
            </form>

            <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">Kod gelmedi mi?</p>
                <form method="POST" action="{{ route('customer.phone.resend') }}">
                    @csrf
                    <button class="inline-flex min-h-11 items-center text-left text-sm font-bold text-[#1746d1] underline decoration-2 underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1746d1]" type="submit">Kodu tekrar gönder</button>
                </form>
            </div>
        </div>
    </div>
@endsection
