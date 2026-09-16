<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title') · Online İşlemler</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3f1eb] text-slate-950 antialiased">
    <a
        href="#content"
        class="fixed left-4 top-4 z-50 -translate-y-24 border-2 border-slate-950 bg-white px-4 py-2 text-sm font-semibold focus:translate-y-0"
    >
        İçeriğe geç
    </a>

    <div class="flex min-h-screen flex-col">
        <header class="border-b-2 border-slate-950 bg-[#f3f1eb]">
            <div class="mx-auto flex min-h-18 w-full max-w-6xl items-center justify-between gap-5 px-5 py-3 sm:px-8">
                <a
                    href="{{ auth('customer')->check() && auth('customer')->user()->phone_verified_at ? route('customer.dashboard') : route('customer.login') }}"
                    class="group flex items-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1746d1]"
                    aria-label="Online İşlemler ana sayfa"
                >
                    <span class="grid size-9 place-items-center bg-[#1746d1] text-xs font-bold tracking-[-0.04em] text-white">Oİ</span>
                    <span class="text-sm font-semibold tracking-[-0.02em] sm:text-base">Online İşlemler</span>
                </a>

                <nav class="flex items-center gap-4 text-sm" aria-label="Hesap menüsü">
                    @auth('customer')
                        @if (auth('customer')->user()->phone_verified_at)
                            <span class="hidden text-slate-600 sm:inline">{{ auth('customer')->user()->first_name }}</span>
                        @endif
                        <form method="POST" action="{{ route('customer.logout') }}">
                            @csrf
                            <button class="border border-slate-950 px-3 py-2 font-semibold hover:bg-slate-950 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1746d1]" type="submit">Çıkış</button>
                        </form>
                    @else
                        @unless (request()->routeIs('customer.login'))
                            <a class="font-semibold underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1746d1]" href="{{ route('customer.login') }}">Giriş</a>
                        @endunless
                        @unless (request()->routeIs('customer.register'))
                            <a class="border border-slate-950 px-3 py-2 font-semibold hover:bg-slate-950 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1746d1]" href="{{ route('customer.register') }}">Kayıt ol</a>
                        @endunless
                    @endauth
                </nav>
            </div>

            @auth('customer')
                @if (auth('customer')->user()->phone_verified_at)
                    <nav class="border-t border-slate-300" aria-label="Online işlemler">
                        <div class="mx-auto flex w-full max-w-6xl gap-1 overflow-x-auto px-5 sm:px-8">
                            @foreach ([
                                ['route' => 'customer.dashboard', 'pattern' => 'customer.dashboard', 'label' => 'Ana sayfa'],
                                ['route' => 'customer.invoices.index', 'pattern' => 'customer.invoices.*', 'label' => 'Faturalar'],
                                ['route' => 'customer.services.index', 'pattern' => 'customer.services.*', 'label' => 'Hizmetler'],
                                ['route' => 'customer.contracts.index', 'pattern' => 'customer.contracts.*', 'label' => 'Sözleşmeler'],
                                ['route' => 'customer.support.index', 'pattern' => 'customer.support.*', 'label' => 'Destek'],
                                ['route' => 'customer.profile', 'pattern' => 'customer.profile*', 'label' => 'Bilgilerim'],
                            ] as $item)
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class([
                                        'shrink-0 border-b-3 px-3 py-3 text-xs font-bold transition focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#1746d1] sm:px-4 sm:text-sm',
                                        'border-[#1746d1] text-[#1746d1]' => request()->routeIs($item['pattern']),
                                        'border-transparent text-slate-600 hover:border-slate-400 hover:text-slate-950' => ! request()->routeIs($item['pattern']),
                                    ])
                                    @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </nav>
                @endif
            @endauth
        </header>

        <main id="content" class="flex flex-1 items-center py-10 sm:py-14">
            <div class="mx-auto w-full max-w-6xl px-5 sm:px-8">
                @error('sms')
                    <div class="mb-5 border-2 border-red-800 bg-red-50 px-4 py-3 text-sm font-semibold text-red-950" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                @if (session('status'))
                    <div class="mb-5 border-2 border-emerald-800 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-950" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-5 border-2 border-red-800 bg-red-50 px-4 py-3 text-sm font-semibold text-red-950" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        <footer class="border-t border-slate-300 py-5">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-1 px-5 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <span>Güvenli müşteri işlemleri</span>
                <span>© {{ now()->year }} Online İşlemler</span>
            </div>
        </footer>
    </div>
</body>
</html>
