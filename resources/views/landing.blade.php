<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNNHS SportsHub</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="site-brand" href="{{ url('/') }}" aria-label="SNNHS SportsHub home">
            <img class="site-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo">
            <span>
                <span class="site-name">SNNHS SportsHub</span>
            </span>
        </a>
        <a class="site-login" href="{{ route('login') }}">Login</a>
    </header>

    <main>
        <section class="hero">
            <h1>Join the Champions</h1>
            <p>Be part of SNNHS's legacy of excellence in sports. Discover your potential, build character, and achieve great things through athletics.</p>
            <a class="primary-button" href="{{ route('application.create') }}">Apply Now <span aria-hidden="true">&rarr;</span></a>
        </section>

        <section class="stats" aria-label="Sports activity summary">
            <article class="info-card"><div class="info-icon" aria-hidden="true">&#127942;</div><div class="info-number">15+</div><div class="info-label">Sports Programs</div></article>
            <article class="info-card"><div class="info-icon" aria-hidden="true">&#128101;</div><div class="info-number">500+</div><div class="info-label">Active Athletes</div></article>
            <article class="info-card"><div class="info-icon" aria-hidden="true">&#128197;</div><div class="info-number">30+</div><div class="info-label">Events Annually</div></article>
        </section>

        <section class="announcements" id="announcements">
            <h2 class="section-title">&#128227; Latest Announcements</h2>
            <div class="announcement-grid">
                <article class="announcement"><div class="announcement-date">May 5, 2026</div><h3>Basketball Tryouts This Friday</h3><p>Basketball team tryouts will be held at the main court this Friday at 3:00 PM. All interested students are welcome to participate.</p></article>
                <article class="announcement"><div class="announcement-date">May 1, 2026</div><h3>Regional Sports Meet - June 2026</h3><p>SNNHS will host the Regional Sports Meet in June. Athletes are encouraged to intensify their training sessions.</p></article>
                <article class="announcement"><div class="announcement-date">April 28, 2026</div><h3>New Sports Equipment Available</h3><p>The school has acquired new training equipment for volleyball and track and field. Check with your coaches for availability.</p></article>
            </div>
        </section>

        <section class="programs" id="programs">
            <h2 class="section-title centered-title">Sports Programs</h2>
            <div class="program-grid">
                <article class="program"><div class="program-icon">&#127936;</div><h3>Basketball</h3><p>Men's and Women's teams</p></article>
                <article class="program"><div class="program-icon">&#127952;</div><h3>Volleyball</h3><p>Indoor and Beach Volleyball</p></article>
                <article class="program"><div class="program-icon">&#127939;</div><h3>Track and Field</h3><p>Various athletic events</p></article>
                <article class="program"><div class="program-icon">&#127992;</div><h3>Badminton</h3><p>Singles and Doubles</p></article>
                <article class="program"><div class="program-icon">&#127991;</div><h3>Table Tennis</h3><p>Competitive play</p></article>
                <article class="program"><div class="program-icon">&#9823;</div><h3>Chess</h3><p>Strategic board game</p></article>
            </div>
        </section>
    </main>

    <footer class="site-footer">&copy; 2026 Surigao del Norte National High School. All rights reserved.<br>Sports Activity Hub - Empowering Athletes, Building Champions</footer>
</body>
</html>
