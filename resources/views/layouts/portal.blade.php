<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SNNHS Sports Hub' }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>
    <svg class="icon-sprite" aria-hidden="true">
        <symbol id="icon-dashboard" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z" /></symbol>
        <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></symbol>
        <symbol id="icon-clipboard" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4.5V3h6v1.5M8 10h8M8 14h6" /></symbol>
        <symbol id="icon-megaphone" viewBox="0 0 24 24"><path d="m3 11 15-5v12L3 14v-3Z" /><path d="M18 10h2a2 2 0 0 1 0 4h-2M6 15l1 5" /></symbol>
        <symbol id="icon-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3" /><path d="M3 20a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5" /></symbol>
        <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3" /><path d="M5 21a7 7 0 0 1 14 0" /></symbol>
        <symbol id="icon-trophy" viewBox="0 0 24 24"><path d="M8 4h8v5a4 4 0 0 1-8 0V4ZM12 13v4M8 21h8M9 17h6M8 6H4v2a4 4 0 0 0 4 4M16 6h4v2a4 4 0 0 1-4 4" /></symbol>
        <symbol id="icon-activity" viewBox="0 0 24 24"><path d="m3 12 4-4 4 8 4-8 3 4h3" /></symbol>
        <symbol id="icon-medical" viewBox="0 0 24 24"><path d="M9 3h6v5h5v6h-5v5H9v-5H4V8h5V3Z" /></symbol>
        <symbol id="icon-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></symbol>
        <symbol id="icon-logout" viewBox="0 0 24 24"><path d="M10 5H5v14h5M14 8l4 4-4 4M9 12h9" /></symbol>
        <symbol id="icon-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></symbol>
        <symbol id="icon-pin" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></symbol>
    </svg>
    <div class="portal">
        <aside class="portal-sidebar">
            <a class="portal-brand" href="{{ $homeUrl ?? url('/') }}">
                <img class="portal-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo">
                <span>
                    <strong>SNNHS</strong>
                    <small>{{ $roleLabel ?? 'Sports Hub' }}</small>
                </span>
            </a>

            <div class="user-chip">
                {{ $userName ?? 'Juan Dela Cruz' }}
                <small>{{ $userRole ?? 'Basketball' }}</small>
            </div>

            <nav class="portal-nav" aria-label="Portal navigation">
                @foreach ($navItems as $item)
                    <a class="{{ $active === $item['key'] ? 'active' : '' }}" href="{{ route($item['route']) }}">
                        <svg class="nav-icon" aria-hidden="true"><use href="#icon-{{ $item['icon'] }}"></use></svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="portal-logout" type="submit"><svg class="nav-icon" aria-hidden="true"><use href="#icon-logout"></use></svg><span>Logout</span></button>
            </form>
        </aside>

        <main class="portal-main">
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('js/offline.js') }}" defer></script>
</body>
</html>
