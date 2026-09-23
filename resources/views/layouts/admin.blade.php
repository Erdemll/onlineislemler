<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ $__env->yieldContent('title') }} · Tepenet Güvenlik Yönetim</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-body">
    <a href="#admin-content" class="portal-skip">İçeriğe geç</a>
    <div class="portal-shell {{ auth()->check() ? 'portal-shell--admin' : 'portal-shell--guest' }}">
        @auth
            <aside class="portal-sidebar" id="admin-menu" data-portal-menu>
                <a class="portal-brand" href="{{ route('admin.dashboard') }}" aria-label="Tepenet Güvenlik yönetim ana sayfa">
                    <span class="portal-brand-mark" aria-hidden="true">T</span>
                    <span class="portal-brand-copy"><strong>TEPENET</strong><small>Yönetim Paneli</small></span>
                </a>
                <nav class="portal-navigation" aria-label="Yönetim menüsü">
                    <span class="portal-navigation-label">Yönetim</span>
                    @foreach ([
                        ['route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'label' => 'Genel bakış', 'icon' => '▦'],
                        ['route' => 'admin.customers.index', 'pattern' => 'admin.customers.*', 'label' => 'Müşteriler', 'icon' => '♙'],
                        ['route' => 'admin.contracts.index', 'pattern' => 'admin.contracts.*', 'label' => 'Sözleşmeler', 'icon' => '▣'],
                        ['route' => 'admin.contract-acceptances.index', 'pattern' => 'admin.contract-acceptances.*', 'label' => 'İmzalananlar', 'icon' => '✓'],
                        ['route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'label' => 'Destek', 'icon' => '◎'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" @class(['portal-nav-link', 'is-active' => request()->routeIs($item['pattern'])]) @if (request()->routeIs($item['pattern'])) aria-current="page" @endif>
                            <span class="portal-nav-icon" aria-hidden="true">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
                <div class="portal-sidebar-footer">
                    <div class="portal-account"><span class="portal-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="portal-account-text"><strong>{{ auth()->user()->name }}</strong><small>Yönetici</small></span></div>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="portal-logout" type="submit">Güvenli çıkış</button></form>
                </div>
            </aside>
        @endauth

        <div class="portal-main">
            <header class="portal-topbar">
                @auth
                    <button class="portal-menu-toggle" type="button" aria-controls="admin-menu" aria-expanded="false" aria-label="Menüyü aç" data-portal-menu-toggle><span aria-hidden="true">☰</span></button>
                    <a class="portal-mobile-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('logo.png') }}" alt="Tepenet Güvenlik"></a>
                    <div class="portal-topbar-title"><strong>{{ $__env->yieldContent('title') }}</strong><small>Tepenet Güvenlik · Yönetim Paneli</small></div>
                    <span class="portal-online"><span aria-hidden="true"></span> Yönetici oturumu</span>
                @else
                    <a class="portal-brand portal-brand--header" href="{{ route('admin.login') }}" aria-label="Tepenet Güvenlik yönetim giriş sayfası">
                        <span class="portal-brand-mark" aria-hidden="true">T</span>
                        <span class="portal-brand-copy"><strong>TEPENET</strong><small>Yönetim Paneli</small></span>
                    </a>
                @endauth
            </header>
            <main id="admin-content" class="portal-content portal-page">
                @if (session('status'))<div class="portal-alert portal-alert--success" role="status">{{ session('status') }}</div>@endif
                @if (session('error'))<div class="portal-alert portal-alert--error" role="alert">{{ session('error') }}</div>@endif
                @yield('content')
            </main>
            <footer class="portal-footer"><span>Tepenet Güvenlik · Yönetim Paneli</span><span>© {{ now()->year }} Tepenet Güvenlik</span></footer>
        </div>
    </div>
</body>
</html>
