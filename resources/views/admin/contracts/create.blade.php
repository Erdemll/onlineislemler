@extends('layouts.admin')

@section('title', 'Sözleşme sürümü yükle')

@section('content')
    <header class="grid gap-4 border-b-2 border-slate-950 pb-7">
        <a class="inline-flex min-h-11 w-fit items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('admin.contracts.index') }}">← Sözleşmelere dön</a>
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yeni yayın</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.05em]">Sözleşme sürümü yükle</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">PDF özel depolamada saklanır, SHA-256 özeti alınır ve yayımlandıktan sonra değiştirilemez.</p>
        </div>
    </header>

    <form class="grid gap-7" method="POST" action="{{ route('admin.contracts.store') }}" enctype="multipart/form-data" data-admin-contract-form>
        @csrf

        <section class="grid gap-5 border-2 border-slate-950 bg-white p-5 sm:p-7">
            <div>
                <p class="text-xs font-black uppercase tracking-wider text-[#1746d1]">01 / Sözleşme</p>
                <h2 class="mt-2 text-2xl font-black">Yeni sözleşme mi, yeni sürüm mü?</h2>
            </div>

            @php($contractMode = old('contract_mode', $contracts->isEmpty() ? 'new' : 'existing'))
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (['existing' => ['Mevcut sözleşme', 'Seçilen sözleşmeye yeni sürüm ekler.'], 'new' => ['Yeni sözleşme', 'Yeni sözleşme ve ilk sürümünü oluşturur.']] as $value => [$label, $description])
                    <label class="cursor-pointer border-2 border-slate-950 p-4 has-[:checked]:bg-slate-950 has-[:checked]:text-white">
                        <input class="sr-only" type="radio" name="contract_mode" value="{{ $value }}" @checked($contractMode === $value)>
                        <span class="block font-black">{{ $label }}</span>
                        <span class="mt-1 block text-xs opacity-70">{{ $description }}</span>
                    </label>
                @endforeach
            </div>
            @error('contract_mode') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror

            <div class="grid gap-2" data-contract-mode="existing">
                <label class="text-sm font-bold" for="contract_id">Sözleşme</label>
                <select class="min-h-12 w-full border-2 border-slate-950 bg-white px-3.5 text-base outline-none focus:border-[#1746d1]" id="contract_id" name="contract_id">
                    <option value="">Sözleşme seçin</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected((string) old('contract_id') === (string) $contract->id)>{{ $contract->name }}</option>
                    @endforeach
                </select>
                @error('contract_id') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-2" data-contract-mode="new">
                <label class="text-sm font-bold" for="new_contract_name">Yeni sözleşme adı</label>
                <input class="min-h-12 w-full border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1]" id="new_contract_name" name="new_contract_name" value="{{ old('new_contract_name') }}" maxlength="255">
                @error('new_contract_name') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-bold" for="version">Sürüm</label>
                    <input class="min-h-12 w-full border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1]" id="version" name="version" value="{{ old('version', '1.0') }}" placeholder="1.0" maxlength="30" required>
                    @error('version') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-bold" for="effective_at">Geçerlilik tarihi</label>
                    <input class="min-h-12 w-full border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1]" id="effective_at" type="datetime-local" name="effective_at" value="{{ old('effective_at', now()->format('Y-m-d\TH:i')) }}" required>
                    @error('effective_at') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-2">
                <label class="text-sm font-bold" for="document">Sözleşme PDF</label>
                <input class="min-h-14 w-full border-2 border-dashed border-slate-950 bg-slate-50 p-3 text-sm file:mr-4 file:border-0 file:bg-slate-950 file:px-4 file:py-2 file:font-bold file:text-white" id="document" type="file" name="document" accept="application/pdf,.pdf" required>
                <p class="text-xs text-slate-500">En fazla 20 MB. Dosya özel depolamada tutulur.</p>
                @error('document') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="grid gap-5 border-2 border-slate-950 bg-white p-5 sm:p-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-[#1746d1]">02 / Ürün ilişkisi</p>
                    <h2 class="mt-2 text-2xl font-black">Cari Plus ürünlerini seçin</h2>
                </div>
                <button class="inline-flex min-h-11 items-center border-2 border-slate-950 px-4 text-xs font-black disabled:cursor-not-allowed disabled:border-slate-300 disabled:text-slate-400" type="submit" form="sync-products-form" @disabled(! $canSyncProducts)>Ürünleri yenile</button>
            </div>

            @error('service_ids') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
            @error('service_ids.*') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror

            <div class="grid gap-3 md:grid-cols-2">
                @forelse ($services as $service)
                    <label class="flex cursor-pointer items-start gap-3 border-2 border-slate-300 p-4 has-[:checked]:border-slate-950 has-[:checked]:bg-[#f4ffd1]">
                        <input class="mt-1 size-5 shrink-0 accent-[#1746d1]" type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array((string) $service->id, array_map('strval', old('service_ids', [])), true))>
                        <span class="min-w-0">
                            <span class="block font-black">{{ $service->name }}</span>
                            <span class="mt-1 block text-xs text-slate-600">Cari Plus #{{ $service->cari_plus_product_id }} @if ($service->cari_plus_sku) · {{ $service->cari_plus_sku }} @endif</span>
                            <span class="mt-2 block text-sm font-bold">{{ number_format((float) $service->price, 2, ',', '.') }} {{ $service->currency }}</span>
                        </span>
                    </label>
                @empty
                    <p class="border-2 border-amber-700 bg-amber-50 p-4 text-sm text-amber-950 md:col-span-2">İlişkilendirilebilecek aktif Cari Plus ürünü yok. Önce ürün kataloğunu yenileyin.</p>
                @endforelse
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a class="inline-flex min-h-12 items-center justify-center border-2 border-slate-950 px-5 text-sm font-black" href="{{ route('admin.contracts.index') }}">Vazgeç</a>
            <button class="inline-flex min-h-12 items-center justify-center bg-[#1746d1] px-6 text-sm font-black text-white hover:bg-slate-950 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit" @disabled($services->isEmpty())>PDF’yi yükle ve yayımla</button>
        </div>
    </form>

    <form class="hidden" id="sync-products-form" method="POST" action="{{ route('admin.products.sync') }}">
        @csrf
    </form>
@endsection
