@extends('layouts.customer')

@section('title', 'Yeni destek talebi')

@section('content')
    <div class="grid gap-7">
        <header class="grid gap-4 border-b-2 border-slate-950 pb-7">
            <a class="inline-flex min-h-11 w-fit items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('customer.support.index') }}">← Destek taleplerine dön</a>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yeni kayıt</p>
                <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">Nasıl yardımcı olabiliriz?</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Konuyu ve yaşadığınız durumu açıkça yazın. Yanıtları aynı talep üzerinden takip edebilirsiniz.</p>
            </div>
        </header>

        <form class="grid gap-6 border-2 border-slate-950 bg-white p-5 sm:p-8" method="POST" action="{{ route('customer.support.store') }}">
            @csrf

            <fieldset class="grid gap-3">
                <legend class="mb-2 text-sm font-black">Destek konusu</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($categories as $category)
                        <label class="cursor-pointer border-2 border-slate-300 p-4 has-[:checked]:border-slate-950 has-[:checked]:bg-[#e8edff]">
                            <input class="sr-only" type="radio" name="category" value="{{ $category->value }}" @checked(old('category') === $category->value)>
                            <span class="block font-black">{{ $category->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('category') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            </fieldset>

            <div class="grid gap-2">
                <label class="text-sm font-black" for="subject">Talep başlığı</label>
                <input class="min-h-12 w-full border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1]" id="subject" name="subject" value="{{ old('subject') }}" maxlength="160" required>
                @error('subject') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-2">
                <label class="text-sm font-black" for="message">Açıklama</label>
                <textarea class="min-h-44 w-full resize-y border-2 border-slate-950 p-3.5 text-base leading-6 outline-none focus:border-[#1746d1]" id="message" name="message" maxlength="5000" required>{{ old('message') }}</textarea>
                <div class="flex justify-between gap-3 text-xs text-slate-500"><span>Hassas parola veya kart bilgisi paylaşmayın.</span><span>En fazla 5000 karakter</span></div>
                @error('message') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a class="inline-flex min-h-12 items-center justify-center border-2 border-slate-950 px-5 text-sm font-black" href="{{ route('customer.support.index') }}">Vazgeç</a>
                <button class="inline-flex min-h-12 items-center justify-center bg-[#1746d1] px-6 text-sm font-black text-white hover:bg-slate-950" type="submit">Talebi oluştur</button>
            </div>
        </form>
    </div>
@endsection
