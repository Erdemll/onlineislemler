@extends('layouts.admin')

@section('title', 'Yönetici girişi')

@section('content')
    <section class="grid w-full max-w-4xl overflow-hidden border-2 border-slate-950 bg-white md:grid-cols-[0.8fr_1.2fr]">
        <div class="flex flex-col justify-between gap-10 bg-slate-950 p-7 text-white sm:p-10">
            <div class="grid gap-5">
                <span class="grid size-12 place-items-center bg-[#d7ff43] text-sm font-black text-slate-950">YP</span>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#d7ff43]">Yetkili erişim</p>
                <h1 class="text-4xl font-black leading-none tracking-[-0.05em]">Yönetim paneli.</h1>
                <p class="text-sm leading-6 text-slate-300">Sözleşme sürümlerini, ürün ilişkilerini ve imzalanmış belgeleri yönetin.</p>
            </div>
            <p class="border-t border-slate-700 pt-5 text-xs leading-5 text-slate-400">Bu ekran yalnızca yetkilendirilmiş yönetici hesaplarına açıktır.</p>
        </div>

        <form class="grid content-center gap-6 p-7 sm:p-10" method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <div>
                <p class="text-sm font-bold text-[#1746d1]">Yönetici hesabı</p>
                <h2 class="mt-1 text-2xl font-black tracking-[-0.03em]">Giriş bilgileri</h2>
            </div>

            <div class="grid gap-2">
                <label class="text-sm font-bold" for="email">E-posta</label>
                <input class="min-h-12 w-full border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/20" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus>
                @error('email') <p class="text-sm font-semibold text-red-800">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-2">
                <label class="text-sm font-bold" for="password">Parola</label>
                <input class="min-h-12 w-full border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/20" id="password" type="password" name="password" autocomplete="current-password" required>
                @error('password') <p class="text-sm font-semibold text-red-800">{{ $message }}</p> @enderror
            </div>

            <button class="inline-flex min-h-12 items-center justify-center bg-[#1746d1] px-5 text-sm font-black text-white hover:bg-slate-950" type="submit">Yönetim paneline gir</button>
            <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('customer.login') }}">Müşteri girişine dön</a>
        </form>
    </section>
@endsection
