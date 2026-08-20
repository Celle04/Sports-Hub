<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports &amp; Analytics &ndash; Sports Activity Hub</title>
    <link rel="stylesheet" href="{{ asset('css/sportshub.css') }}">
</head>
<body>

    <div class="shell">

        <!-- Sidebar -->
        <aside class="side">
            <div class="brand">
                <img class="brand-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo">
                <span>
                    <b>Sports Hub</b>
                    <small>Administrator Panel</small>
                </span>
            </div>

            <nav class="nav">
                <a href="{{ route('dashboard') }}">
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
                <a class="active" href="{{ route('reports.index') }}">
                    <span class="icon">&#128202;</span>Reports
                </a>
                <a class="logout" href="{{ url('/') }}">
                    <span class="icon">&#128682;</span>Logout
                </a>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="content">

            <div class="heading">
                <div>
                    <h1>Reports &amp; Analytics</h1>
                    <p class="sub">View participation statistics and event summaries</p>
                </div>
                <button class="button print">&#128424;&nbsp; Print Report</button>
            </div>

            <section class="stats">
                <article class="stat">
                    <small>Total Athletes</small>
                    <b>145</b>
                    <i class="blue">&#127939;</i>
                </article>

                <article class="stat">
                    <small>Total Events</small>
                    <b>12</b>
                    <i class="green">&#128197;</i>
                </article>

                <article class="stat">
                    <small>Active Sports</small>
                    <b>12</b>
                    <i class="purple">&#9917;</i>
                </article>
            </section>

            <section class="chart">
                <h2>Athlete Participation by Sport</h2>

                <div class="plot">
                    <div class="bar-item">
                        <div class="column" style="height:72%"></div>
                        <span class="label">Basketball</span>
                    </div>
                    <div class="bar-item">
                        <div class="column" style="height:64%"></div>
                        <span class="label">Volleyball</span>
                    </div>
                    <div class="bar-item">
                        <div class="column" style="height:51%"></div>
                        <span class="label">Track &amp; Field</span>
                    </div>
                    <div class="bar-item">
                        <div class="column" style="height:34%"></div>
                        <span class="label">Badminton</span>
                    </div>
                    <div class="bar-item">
                        <div class="column" style="height:17%"></div>
                        <span class="label">Others</span>
                    </div>
                </div>
            </section>

        </main>
    </div>

</body>
</html>
