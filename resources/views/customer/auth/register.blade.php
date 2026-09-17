@extends('layouts.customer')

@section('title', 'Hesap oluştur')

@section('content')
    <div class="grid overflow-hidden border-2 border-slate-950 bg-white lg:grid-cols-[0.62fr_1.38fr]">
        <div class="flex flex-col justify-between gap-12 bg-[#1746d1] p-7 text-white sm:p-10">
            <div class="grid gap-5">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-100">Yeni müşteri</p>
                <h1 class="max-w-sm text-4xl font-semibold leading-[1.05] tracking-[-0.045em] sm:text-5xl">İşlemlerin tek yerde.</h1>
                <p class="max-w-sm text-sm leading-6 text-blue-100">Bireysel veya kurumsal hesabını oluştur. E-posta doğrulamasından sonra Cari Plus müşteri hesabın otomatik hazırlanır.</p>
            </div>
            <ol class="grid gap-3 border-t border-blue-300/50 pt-5 text-sm" aria-label="Kayıt adımları">
                <li class="flex gap-3"><span class="font-bold">01</span><span>Hesap bilgileri</span></li>
                <li class="flex gap-3 text-blue-100"><span class="font-bold">02</span><span>E-posta doğrulama</span></li>
                <li class="flex gap-3 text-blue-100"><span class="font-bold">03</span><span>Müşteri hesabı</span></li>
            </ol>
        </div>

        <div class="p-7 sm:p-10 lg:p-14">
            <form class="mx-auto grid max-w-3xl gap-8" method="POST" action="{{ route('customer.register.store') }}" data-registration-form>
                @csrf

                <div class="grid gap-2">
                    <p class="text-sm font-semibold text-[#1746d1]">Hesap oluştur</p>
                    <h2 class="text-2xl font-semibold tracking-[-0.03em]">Müşteri bilgileri</h2>
                </div>

                <fieldset class="grid gap-3">
                    <legend class="mb-2 text-sm font-semibold">Hesap türü</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (['individual' => ['Bireysel', 'Kendi adınıza işlem yapın.'], 'corporate' => ['Kurumsal', 'Şirket veya kamu kurumu adına işlem yapın.']] as $value => [$label, $description])
                            <label class="cursor-pointer border-2 border-slate-950 p-4 has-[:checked]:bg-slate-950 has-[:checked]:text-white">
                                <input class="sr-only" type="radio" name="account_type" value="{{ $value }}" @checked(old('account_type', 'individual') === $value)>
                                <span class="block font-semibold">{{ $label }}</span>
                                <span class="mt-1 block text-xs opacity-70">{{ $description }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('account_type') <p class="text-sm font-medium text-red-800">{{ $message }}</p> @enderror
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-customer.input name="first_name" label="Ad / yetkili adı" :value="old('first_name')" autocomplete="given-name" required autofocus />
                    <x-customer.input name="last_name" label="Soyad / yetkili soyadı" :value="old('last_name')" autocomplete="family-name" required />
                </div>

                <div class="grid gap-5" data-account-fields="individual">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="national_id" label="T.C. kimlik numarası" :value="old('national_id')" inputmode="numeric" maxlength="11" data-required />
                        <x-customer.input name="individual_company_title" label="Firma ünvanı" hint="Opsiyonel" :value="old('account_type', 'individual') === 'individual' ? old('company_title') : null" />
                    </div>
                </div>

                <div class="grid gap-5" data-account-fields="corporate">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="tax_number" label="Vergi numarası" :value="old('tax_number')" inputmode="numeric" maxlength="10" data-required />
                        <x-customer.input name="tax_office" label="Vergi dairesi" :value="old('tax_office')" data-required />
                    </div>
                    <x-customer.input name="corporate_company_title" label="Firma ünvanı" :value="old('account_type') === 'corporate' ? old('company_title') : null" data-required />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="mobile_phone" label="Mobil telefon" type="tel" hint="Opsiyonel" :value="old('mobile_phone')" autocomplete="tel" inputmode="tel" />
                        <label class="flex min-h-12 items-center gap-3 border-2 border-slate-950 px-4 py-3 text-sm font-semibold">
                            <input class="size-4 accent-[#1746d1]" type="checkbox" name="is_public_institution" value="1" @checked(old('is_public_institution'))>
                            Kamu kurumu
                        </label>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-customer.input name="spending_unit_tax_number" label="Harcama birimi VKN" hint="Opsiyonel" :value="old('spending_unit_tax_number')" inputmode="numeric" maxlength="10" />
                        <x-customer.input name="spending_unit_title" label="Harcama birimi ünvanı" hint="Opsiyonel" :value="old('spending_unit_title')" />
                    </div>
                </div>

                <input type="hidden" name="company_title" value="" data-company-title>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-customer.input name="phone" label="Telefon" type="tel" hint="0(5xx) veya 0(2xx)" :value="old('phone')" autocomplete="tel" inputmode="tel" required />
                    <x-customer.input name="email" label="E-posta" type="email" :value="old('email')" autocomplete="email" inputmode="email" required />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-semibold" for="province_code">İl</label>
                        <select class="min-h-12 w-full border-2 border-slate-950 bg-white px-3.5 py-2.5 outline-none focus:border-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/20" id="province_code" name="province_code" required>
                            <option value="">İl seçin</option>
                            @foreach ($provinces as $code => $province)
                                <option value="{{ $code }}" @selected(old('province_code') === $code)>{{ $province }}</option>
                            @endforeach
                        </select>
                        @error('province_code') <p class="text-sm font-medium text-red-800">{{ $message }}</p> @enderror
                    </div>
                    <x-customer.input name="district" label="İlçe" :value="old('district')" autocomplete="address-level2" required />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-semibold" for="address_line">Açık adres</label>
                    <textarea class="min-h-28 w-full resize-y border-2 border-slate-950 bg-white px-3.5 py-2.5 outline-none focus:border-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/20" id="address_line" name="address_line" autocomplete="street-address" required>{{ old('address_line') }}</textarea>
                    @error('address_line') <p class="text-sm font-medium text-red-800">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-customer.input name="password" label="Şifre" type="password" hint="En az 12 karakter" autocomplete="new-password" required />
                    <x-customer.input name="password_confirmation" label="Şifre tekrar" type="password" autocomplete="new-password" required />
                </div>

                <x-customer.submit class="sm:w-auto sm:min-w-44" label="Hesap oluştur" />
                <p class="flex flex-wrap items-center gap-x-1 border-t border-slate-200 pt-5 text-sm text-slate-600">Zaten hesabın var mı? <a class="inline-flex min-h-11 items-center font-bold text-slate-950 underline decoration-2 underline-offset-4" href="{{ route('customer.login') }}">Giriş yap</a></p>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-registration-form]');
            if (!form) return;

            const sync = () => {
                const type = form.querySelector('[name="account_type"]:checked').value;
                form.querySelectorAll('[data-account-fields]').forEach((section) => {
                    const active = section.dataset.accountFields === type;
                    section.hidden = !active;
                    section.querySelectorAll('input').forEach((input) => {
                        input.disabled = !active;
                        if (input.hasAttribute('data-required')) input.required = active;
                    });
                });
                const source = form.querySelector(`[name="${type}_company_title"]`);
                form.querySelector('[data-company-title]').value = source?.value ?? '';
            };

            form.querySelectorAll('[name="account_type"], [name$="_company_title"]').forEach((input) => input.addEventListener('input', sync));
            form.addEventListener('submit', sync);
            sync();
        })();
    </script>
@endsection
