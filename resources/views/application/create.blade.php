<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Sports Program</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <style>
        .application-page { width: min(100% - 32px, 620px); margin: 40px auto 70px; }
        .application-card { padding: 28px; border: 1px solid var(--line); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .application-card h1 { margin: 0 0 8px; font-size: 28px; }
        .application-card p { color: var(--muted); }
        .application-form { display: grid; gap: 15px; margin-top: 24px; }
        .application-form label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; }
        .application-form input, .application-form select { width: 100%; padding: 12px; border: 1px solid var(--line); border-radius: 4px; font: inherit; }
        .success-message { padding: 12px; border-radius: 4px; color: #176b3a; background: #e8f7ee; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="site-brand" href="{{ url('/') }}"><img class="site-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo"><span><span class="site-name">SNNHS SportsHub</span></span></a>
        <a class="site-login" href="{{ route('login') }}">Login</a>
    </header>
    <main class="application-page">
        <section class="application-card">
            <h1>Sports Program Application</h1>
            <p>Tell us about yourself and the sport you want to join.</p>
            @if (session('submitted'))
                <div class="success-message">Your application was submitted successfully. Our sports coordinators will contact you soon.</div>
            @endif
            <form class="application-form" method="POST" action="{{ route('application.store') }}">
                @csrf
                <label>Full name<input name="name" type="text" required></label>
                <label>Grade level<input name="grade" type="text" required></label>
                <label>Email address<input name="email" type="email" required></label>
                <label>Preferred sport<select name="sport" required><option value="">Choose a sport</option>@foreach($sports as $sport)<option value="{{ $sport->name }}" @selected(old('sport') === $sport->name)>{{ $sport->name }}</option>@endforeach</select></label>
                <button class="primary-button" type="submit">Submit Application</button>
            </form>
        </section>
    </main>
    <script src="{{ asset('js/offline.js') }}" defer></script>
</body>
</html>
