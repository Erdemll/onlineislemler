<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ $__env->yieldContent('title') }} · Tepenet Güvenlik Online İşlemler</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-body">
    <a href="#content" class="portal-skip">İçeriğe geç</a>

    @php($hasCustomerNavigation = auth('customer')->check() && auth('customer')->user()->email_verified_at)
    <div class="portal-shell {{ $hasCustomerNavigation ? 'portal-shell--customer' : 'portal-shell--guest' }}">
        @if ($hasCustomerNavigation)
            <aside class="portal-sidebar" id="customer-menu" data-portal-menu>
                <a class="portal-brand" href="{{ route('customer.dashboard') }}" aria-label="Tepenet Güvenlik Online İşlemler ana sayfa">
                    <span class="portal-brand-mark" aria-hidden="true">T</span>
                    <span class="portal-brand-copy"><strong>TEPENET</strong><small>Online İşlemler</small></span>
                </a>

                <nav class="portal-navigation" aria-label="Online işlemler">
                    <span class="portal-navigation-label">Hesabım</span>
                    @foreach ([
                        ['route' => 'customer.dashboard', 'patterns' => ['customer.dashboard'], 'label' => 'Ana sayfa', 'icon' => '▦'],
                        ['route' => 'customer.invoices.index', 'patterns' => ['customer.invoices.*'], 'label' => 'Faturalar', 'icon' => '▤'],
                        ['route' => 'customer.services.index', 'patterns' => ['customer.services.*'], 'label' => 'Hizmetler', 'icon' => '◇'],
                        ['route' => 'customer.contracts.index', 'patterns' => ['customer.contracts.*', 'customer.service-orders.contract.*'], 'label' => 'Sözleşmeler', 'icon' => '▣'],
                        ['route' => 'customer.support.index', 'patterns' => ['customer.support.*'], 'label' => 'Destek', 'icon' => '◎'],
                        ['route' => 'customer.profile', 'patterns' => ['customer.profile*', 'customer.phone.change.*'], 'label' => 'Bilgilerim', 'icon' => '♙'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" @class(['portal-nav-link', 'is-active' => request()->routeIs(...$item['patterns'])]) @if (request()->routeIs(...$item['patterns'])) aria-current="page" data-customer-navigation-active @endif>
                            <span class="portal-nav-icon" aria-hidden="true">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="portal-sidebar-footer">
                    <div class="portal-account"><span class="portal-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth('customer')->user()->first_name, 0, 1)) }}</span><span class="portal-account-text"><strong>{{ auth('customer')->user()->first_name }}</strong><small>Müşteri hesabı</small></span></div>
                    <form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="portal-logout" type="submit">Güvenli çıkış</button></form>
                </div>
            </aside>
        @endif

        <div class="portal-main">
            <header class="portal-topbar">
                @if ($hasCustomerNavigation)
                    <button class="portal-menu-toggle" type="button" aria-controls="customer-menu" aria-expanded="false" aria-label="Menüyü aç" data-portal-menu-toggle><span aria-hidden="true">☰</span></button>
                    <a class="portal-mobile-brand" href="{{ route('customer.dashboard') }}"><img src="{{ asset('logo.png') }}" alt="Tepenet Güvenlik"></a>
                    <div class="portal-topbar-title"><strong>{{ $__env->yieldContent('title') }}</strong><small>Tepenet Güvenlik · Online İşlemler</small></div>
                    <span class="portal-online"><span aria-hidden="true"></span> Güvenli oturum</span>
                @else
                    <a class="portal-brand portal-brand--header" href="{{ route('customer.login') }}" aria-label="Tepenet Güvenlik Online İşlemler ana sayfa">
                        <span class="portal-brand-mark" aria-hidden="true">T</span>
                        <span class="portal-brand-copy"><strong>TEPENET</strong><small>Online İşlemler</small></span>
                    </a>
                    <nav class="portal-guest-links" aria-label="Hesap menüsü">
                        @auth('customer')
                            <form method="POST" action="{{ route('customer.logout') }}">@csrf<button type="submit">Çıkış</button></form>
                        @else
                            @unless (request()->routeIs('customer.login'))<a href="{{ route('customer.login') }}">Giriş</a>@endunless
                            @unless (request()->routeIs('customer.register'))<a href="{{ route('customer.register') }}">Kayıt ol</a>@endunless
                        @endauth
                    </nav>
                @endif
            </header>

            <main id="content" class="portal-content portal-page">
                @error('sms')<div class="portal-alert portal-alert--error" role="alert">{{ $message }}</div>@enderror
                @error('email_delivery')<div class="portal-alert portal-alert--error" role="alert">{{ $message }}</div>@enderror
                @if (session('status'))<div class="portal-alert portal-alert--success" role="status">{{ session('status') }}</div>@endif
                @if (session('error'))<div class="portal-alert portal-alert--error" role="alert">{{ session('error') }}</div>@endif
                @yield('content')
            </main>
            <footer class="portal-footer"><span>Tepenet Güvenlik · Güvenli müşteri işlemleri</span><span>© {{ now()->year }} Tepenet Güvenlik</span></footer>
        </div>
    </div>
</body>
</html>
