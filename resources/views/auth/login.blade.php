<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNNHS Sports Hub Login</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <style>
        .login-page { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #fff; }
        .login-card { width: min(100%, 390px); padding: 30px; border: 1px solid var(--line); border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .login-logo { display: block; width: 58px; height: 58px; margin: 0 auto 16px; object-fit: contain; }
        .login-card h1 { text-align: center; font-size: 24px; }
        .login-card .subtitle { margin: 7px 0 24px; text-align: center; }
        .login-card label { display: block; margin: 0 0 6px; font-size: 11px; font-weight: 700; }
        .login-card input, .login-card select { width: 100%; margin-bottom: 14px; padding: 11px; border: 1px solid var(--line); border-radius: 6px; background: #f7f7f8; font: inherit; font-size: 11px; }
        .login-card .button { width: 100%; }
        .login-back { display: block; margin-top: 18px; text-align: center; color: var(--red); font-size: 11px; text-decoration: none; }
    </style>
</head>
<body>
    
    <main class="login-page">
        <form class="login-card" method="POST" action="{{ route('login.submit') }}" aria-label="Sports Activity Hub sign in form">
            @csrf
            <img class="login-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo">
            <h1>Welcome Back</h1>
            <p class="subtitle">Sign in to access your dashboard</p>
            <label for="role">Login As</label>
            <select id="role" name="role"><option>Administrator</option><option>Student</option></select>
            <label for="username">Email Address</label>
            <input id="username" name="username" type="email" placeholder="your.email@snhhs.edu.ph" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
            <button class="button" type="submit">Sign In</button>
            <a class="login-back" href="{{ url('/') }}">&larr; Back to Home</a>
        </form>
    </main>
    <script src="{{ asset('js/offline.js') }}" defer></script>
</body>
</html>
