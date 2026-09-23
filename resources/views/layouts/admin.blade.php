<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title') · Tepenet Güvenlik Yönetim</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#ecebe6] text-slate-950 antialiased">
    <a href="#admin-content" class="fixed left-4 top-4 z-50 -translate-y-24 border-2 border-slate-950 bg-white px-4 py-2 text-sm font-bold focus:translate-y-0">İçeriğe geç</a>

    @auth
        <div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
            <aside class="border-b-2 border-slate-950 bg-slate-950 text-white lg:min-h-screen lg:border-b-0 lg:border-r-2">
                <div class="flex min-h-18 items-center justify-between gap-4 border-b border-slate-700 px-5 py-3 lg:px-6">
                    <a class="flex min-w-0 items-center gap-3" href="{{ route('admin.dashboard') }}" aria-label="Tepenet Güvenlik yönetim ana sayfa">
                        <span class="grid shrink-0 place-items-center bg-white px-2 py-1">
                            <img class="h-7 w-auto" src="{{ asset('logo.png') }}" alt="" aria-hidden="true">
                        </span>
                        <span class="grid min-w-0 leading-tight">
                            <span class="truncate text-sm font-black tracking-[-0.02em]">Tepenet Güvenlik</span>
                            <span class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400">Yönetim</span>
                        </span>
                    </a>
                    <form class="lg:hidden" method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="inline-flex min-h-11 items-center border border-slate-500 px-3 text-xs font-bold" type="submit">Çıkış</button>
                    </form>
                </div>

                <nav class="flex gap-1 overflow-x-auto px-4 py-3 lg:grid lg:gap-2 lg:overflow-visible lg:px-5 lg:py-6" aria-label="Yönetim menüsü">
                    @foreach ([
                        ['route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'label' => 'Genel bakış', 'no' => '01'],
                        ['route' => 'admin.contracts.index', 'pattern' => 'admin.contracts.*', 'label' => 'Sözleşmeler', 'no' => '02'],
                        ['route' => 'admin.contract-acceptances.index', 'pattern' => 'admin.contract-acceptances.*', 'label' => 'İmzalananlar', 'no' => '03'],
                        ['route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'label' => 'Destek', 'no' => '04'],
                        ['route' => 'admin.customers.index', 'pattern' => 'admin.customers.*', 'label' => 'Müşteriler', 'no' => '05'],
                    ] as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'inline-flex min-h-11 shrink-0 items-center gap-3 border px-4 text-sm font-bold transition lg:w-full',
                                'border-[#d7ff43] bg-[#d7ff43] text-slate-950' => request()->routeIs($item['pattern']),
                                'border-slate-700 text-slate-300 hover:border-slate-400 hover:text-white' => ! request()->routeIs($item['pattern']),
                            ])
                            @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
                        >
                            <span class="font-mono text-[0.65rem] opacity-60">{{ $item['no'] }}</span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="hidden border-t border-slate-700 p-5 lg:block">
                    <p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p>
                    <p class="mt-1 truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                    <form class="mt-4" method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="inline-flex min-h-11 w-full items-center justify-center border border-slate-600 text-sm font-bold hover:border-white" type="submit">Güvenli çıkış</button>
                    </form>
                </div>
            </aside>

            <main id="admin-content" class="min-w-0 px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
                <div class="mx-auto grid max-w-6xl gap-6">
                    @if (session('status'))
                        <div class="border-2 border-emerald-800 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-950" role="status">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="border-2 border-red-800 bg-red-50 px-4 py-3 text-sm font-bold text-red-950" role="alert">{{ session('error') }}</div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    @else
        <main id="admin-content" class="grid min-h-screen place-items-center px-5 py-10 sm:px-8">
            @yield('content')
        </main>
    @endauth
</body>
</html>
