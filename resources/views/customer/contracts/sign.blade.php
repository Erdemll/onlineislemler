@extends('layouts.customer')

@section('title', 'Sözleşme onayı')

@section('content')
    <section class="flex flex-col gap-7">
        <header class="grid gap-5 border-b-2 border-slate-950 pb-6 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">Sözleşme onayı</p>
                <h1 class="text-3xl font-black tracking-[-0.045em] sm:text-5xl">{{ $serviceOrder->contractVersion->contract->name }}</h1>
                <p class="mt-3 text-sm text-slate-600">{{ $serviceOrder->service_name_snapshot }} · Sürüm {{ $serviceOrder->contractVersion->version }}</p>
            </div>
            <div class="border-2 border-slate-950 bg-white px-5 py-3">
                <span class="block text-xs font-bold uppercase tracking-wider text-slate-500">Sabitlenen bedel</span>
                <strong class="text-2xl">{{ number_format((float) $serviceOrder->unit_price, 2, ',', '.') }} ₺</strong>
            </div>
        </header>

        @if ($serviceOrder->acceptance)
            <div class="grid gap-5 border-2 border-emerald-800 bg-emerald-50 p-6 sm:p-8">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-emerald-800">Tamamlandı</p>
                <h2 class="text-3xl font-black tracking-[-0.04em]">Sözleşmeniz kabul edildi.</h2>
                <p class="text-sm leading-6 text-emerald-950">Kabul kaydı, e-posta doğrulaması, teknik bilgiler ve PDF bütünlük hash’i güvenli biçimde saklanıyor.</p>
                <a class="w-fit border-2 border-slate-950 bg-slate-950 px-5 py-3 text-sm font-black text-white hover:bg-[#1746d1]" href="{{ route('customer.contracts.download', $serviceOrder->acceptance) }}">İmzalı PDF’yi indir</a>
            </div>
        @elseif ($challenge)
            <div class="mx-auto grid w-full max-w-xl gap-6 border-2 border-slate-950 bg-white p-6 sm:p-8">
                <div class="grid gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Son adım</span>
                    <h2 class="text-2xl font-black tracking-[-0.035em]">E-posta kodunu girin</h2>
                    <p class="text-sm leading-6 text-slate-600">Kayıtlı e-posta adresinize gönderilen altı haneli kodu girin. Kod sözleşme sürümüne ve çizdiğiniz imzaya bağlıdır.</p>
                </div>
                <form class="grid gap-5" method="POST" action="{{ route('customer.service-orders.contract.accept', $serviceOrder) }}">
                    @csrf
                    <input type="hidden" name="challenge_uuid" value="{{ $challenge->uuid }}">
                    <x-customer.input class="text-center font-mono text-2xl font-black tracking-[0.35em]" name="code" label="Onay kodu" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
                    <x-customer.submit label="Kodu doğrula ve sözleşmeyi kabul et" />
                </form>
                <a class="inline-flex min-h-11 items-center text-sm font-bold text-[#1746d1] underline decoration-2 underline-offset-4" href="{{ route('customer.service-orders.contract.show', $serviceOrder) }}">İmzayı yeniden oluştur</a>
            </div>
        @else
            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <div class="overflow-hidden border-2 border-slate-950 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-slate-950 px-4 py-3">
                        <strong class="text-sm">Sözleşmenin tamamı</strong>
                        <a class="inline-flex min-h-11 items-center justify-center border-2 border-slate-950 bg-[#d7ff43] px-3 text-xs font-black text-slate-950 sm:min-h-0 sm:border-0 sm:bg-transparent sm:px-0 sm:text-[#1746d1] sm:underline sm:decoration-2 sm:underline-offset-4" href="{{ route('customer.service-orders.contract.document', $serviceOrder) }}" target="_blank" rel="noopener">PDF’yi tam ekran aç ↗</a>
                    </div>
                    <p class="border-b border-slate-300 bg-slate-100 px-4 py-3 text-xs leading-5 text-slate-600 lg:hidden">Belge küçük görünüyorsa veya sayfalar açılmıyorsa PDF’yi tam ekran görüntüleyin.</p>
                    <iframe class="h-[62vh] min-h-[28rem] w-full sm:h-[68vh] sm:min-h-[34rem]" src="{{ route('customer.service-orders.contract.document', $serviceOrder) }}#toolbar=1&navpanes=0" title="{{ $serviceOrder->contractVersion->contract->name }} PDF belgesi"></iframe>
                </div>

                <form class="flex flex-col gap-5 border-2 border-slate-950 bg-white p-5 sm:p-6" method="POST" action="{{ route('customer.service-orders.contract.challenge', $serviceOrder) }}" data-signature-form>
                    @csrf
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">İmza</p>
                        <h2 class="mt-1 text-2xl font-black tracking-[-0.035em]">Okuyun ve imzalayın</h2>
                    </div>

                    <label class="flex items-start gap-3 border-2 border-slate-950 p-4 text-sm font-semibold leading-5">
                        <input class="mt-0.5 size-4 shrink-0 accent-[#1746d1]" type="checkbox" name="accepted" value="1" @checked(old('accepted')) required>
                        Sözleşmenin tamamını okudum, içeriğini anladım ve kabul ediyorum.
                    </label>
                    @error('accepted') <p class="text-sm font-semibold text-red-800">{{ $message }}</p> @enderror

                    @if (auth('customer')->user()->account_type->value === 'corporate')
                        <label class="flex items-start gap-3 border-2 border-slate-950 p-4 text-sm font-semibold leading-5">
                            <input class="mt-0.5 size-4 shrink-0 accent-[#1746d1]" type="checkbox" name="authority_confirmed" value="1" @checked(old('authority_confirmed')) required>
                            Firma adına bu sözleşmeyi kabul ve imza etmeye yetkili olduğumu beyan ederim.
                        </label>
                        @error('authority_confirmed') <p class="text-sm font-semibold text-red-800">{{ $message }}</p> @enderror
                    @endif

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-semibold" for="signature-canvas">Çizilmiş imza</label>
                            <button class="inline-flex min-h-11 items-center px-2 text-xs font-black text-[#1746d1] underline decoration-2 underline-offset-4" type="button" data-signature-clear>Temizle</button>
                        </div>
                        <div class="overflow-hidden border-2 border-slate-950 bg-white" data-signature-wrapper>
                            <canvas class="block h-48 w-full touch-none" id="signature-canvas" tabindex="0" data-signature-canvas></canvas>
                        </div>
                        <p class="text-xs leading-5 text-slate-500">Fare, dokunmatik ekran veya kalemle kutunun içine imzanızı çizin.</p>
                        <p class="hidden text-sm font-semibold text-red-800" data-signature-error>İmzanızı çizmelisiniz.</p>
                        @error('signature_data') <p class="text-sm font-semibold text-red-800">{{ $message }}</p> @enderror
                    </div>
                    <input type="hidden" name="signature_data" data-signature-data>

                    <button class="mt-auto inline-flex min-h-12 items-center justify-center border-2 border-slate-950 bg-slate-950 px-5 py-3 text-center text-sm font-black text-white transition hover:bg-[#1746d1]" type="submit">Onayla ve e-posta kodu gönder →</button>
                    <p class="text-xs leading-5 text-slate-500">Bu işlem güvenli elektronik imza değil; hesap oturumu, çizilmiş imza ve e-posta OTP ile kayıt altına alınan elektronik sözleşme kabulüdür.</p>
                </form>
            </div>
        @endif
    </section>
@endsection
