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
                        <span class="nav-icon">{!! $item['icon'] !!}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <a class="portal-logout" href="{{ url('/') }}">
                <span class="nav-icon">&#10132;</span>
                <span>Logout</span>
            </a>
        </aside>

        <main class="portal-main">
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('js/offline.js') }}" defer></script>
</body>
</html>
