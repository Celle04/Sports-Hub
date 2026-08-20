<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard &ndash; SportsHub</title>
    <link rel="stylesheet" href="{{ asset('css/sportshub.css') }}">
</head>
<body>
    <div class="shell">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <img class="brand-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo">
                <div>
                    <div class="brand-name">Sports Hub</div>
                    <div class="brand-role">Administrator</div>
                </div>
            </div>

            <nav class="nav" aria-label="Main navigation">
                <a class="active" href="{{ route('dashboard') }}">
                    <span class="icon">&#127968;</span>Dashboard
                </a>
                <a href="{{ route('sports.index') }}">
                    <span class="icon">&#9917;</span>Sports
                </a>
                <a href="{{ route('athletes.index') }}">
                    <span class="icon">&#127939;</span>Athletes
                </a>
                <a href="{{ route('coaches.index') }}">
                    <span class="icon">&#128101;</span>Coaches
                </a>
                <a href="{{ route('events.index') }}">
                    <span class="icon">&#128197;</span>Events
                </a>
                <a href="{{ route('reports.index') }}">
                    <span class="icon">&#128202;</span>Reports
                </a>
                <a class="logout" href="{{ url('/') }}">
                    <span class="icon">&#128682;</span>Logout
                </a>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="content">

            <h1>Dashboard</h1>
            <p class="welcome">Welcome to SNNHS Sports Activity Hub</p>

            <section class="stats" aria-label="Sports summary">
                <article class="stat">
                    <div class="stat-label">Total Sports</div>
                    <div class="stat-number">12</div>
                    <div class="stat-icon blue">&#9917;</div>
                </article>

                <article class="stat">
                    <div class="stat-label">Registered Athletes</div>
                    <div class="stat-number">145</div>
                    <div class="stat-icon green">&#127939;</div>
                </article>

                <article class="stat">
                    <div class="stat-label">Active Coaches</div>
                    <div class="stat-number">18</div>
                    <div class="stat-icon purple">&#128101;</div>
                </article>

                <article class="stat">
                    <div class="stat-label">Upcoming Events</div>
                    <div class="stat-number">7</div>
                    <div class="stat-icon orange">&#128197;</div>
                </article>
            </section>

            <section class="events" aria-label="Upcoming events">
                <h2>Upcoming Events</h2>

                <div class="event">
                    <div>
                        <div class="event-title">Basketball Intramurals</div>
                        <div class="event-venue">Main Gym</div>
                    </div>
                    <div class="event-date">May 15, 2026</div>
                </div>

                <div class="event">
                    <div>
                        <div class="event-title">Track &amp; Field Meet</div>
                        <div class="event-venue">School Oval</div>
                    </div>
                    <div class="event-date">May 20, 2026</div>
                </div>

                <div class="event">
                    <div>
                        <div class="event-title">Volleyball Finals</div>
                        <div class="event-venue">Covered Court</div>
                    </div>
                    <div class="event-date">May 25, 2026</div>
                </div>
            </section>

        </main>
    </div>

</body>
</html>
