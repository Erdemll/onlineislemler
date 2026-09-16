@extends('layouts.customer')

@section('title', 'Telefon değişikliğini doğrula')

@section('content')
    <div class="mx-auto max-w-3xl border-2 border-slate-950 bg-white">
        <div class="border-b-2 border-slate-950 bg-slate-100 p-7 sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">Güvenlik kontrolü</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-[-0.04em]">Yeni telefonu doğrula</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Yeni telefon numarana gönderdiğimiz altı haneli kodu gir.</p>
        </div>
        <div class="grid gap-6 p-7 sm:p-9">
            <form class="grid gap-5" method="POST" action="{{ route('customer.phone.change.verify') }}">
                @csrf
                <x-customer.input class="text-center font-mono text-2xl font-semibold tracking-[0.35em]" name="code" label="Doğrulama kodu" type="text" hint="6 hane" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus />
                <x-customer.submit label="Telefon numarasını değiştir" />
            </form>
            <a class="text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('customer.profile') }}">Profil sayfasına dön</a>
        </div>
    </div>
@endsection
