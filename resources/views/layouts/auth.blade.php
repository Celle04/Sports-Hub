<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SNNHS Sports Hub')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body class="auth-body">
    <svg class="icon-sprite" aria-hidden="true">
        <symbol id="icon-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></symbol>
        <symbol id="icon-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" /></symbol>
        <symbol id="icon-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></symbol>
        <symbol id="icon-eye-off" viewBox="0 0 24 24"><path d="m3 3 18 18" /><path d="M10.6 10.7a2 2 0 0 0 2.8 2.8" /><path d="M9.9 4.3A10.6 10.6 0 0 1 12 4c6.4 0 10 8 10 8a17.6 17.6 0 0 1-2.3 3.2M6.6 6.7A17.4 17.4 0 0 0 2 12s3.6 8 10 8a10.4 10.4 0 0 0 4.3-.9" /></symbol>
        <symbol id="icon-arrow-left" viewBox="0 0 24 24"><path d="M20 12H4M11 19l-7-7 7-7" /></symbol>
        <symbol id="icon-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.4 3 7.7 7 9 4-1.3 7-4.6 7-9V6l-7-3Z" /><path d="m9 12 2 2 4-4" /></symbol>
    </svg>

    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="auth-title">
            <header class="auth-brand">
                <img class="auth-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo">
                <h1 id="auth-title">SNNHS SPORTS ACTIVITY HUB</h1>
                <p>Sports Management System</p>
            </header>

            @if (session('success'))
                <div class="auth-alert auth-alert-success" role="status">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="auth-alert auth-alert-error" role="alert">Please correct the highlighted fields below.</div>
            @endif

            @yield('content')
        </section>

        <footer class="auth-footer">SNNHS Sports Activity Hub</footer>
    </main>

    <script src="{{ asset('js/offline.js') }}" defer></script>
    <script src="{{ asset('js/auth.js') }}" defer></script>
</body>
</html>