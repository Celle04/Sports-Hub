<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Scheduling &ndash; Sports Activity Hub</title>
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
                <a class="active" href="{{ route('events.index') }}">
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

            <div class="heading">
                <div>
                    <h1>Event Scheduling</h1>
                    <p class="sub">Schedule and manage sports events</p>
                </div>
                <button class="button">&#43;&nbsp; Create Event</button>
            </div>

            <section class="panel">
                <h2>&#128197;&nbsp; Scheduled Events</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Sport</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basketball Intramurals</td>
                            <td>May 15, 2026</td>
                            <td>Main Gym</td>
                            <td>Basketball</td>
                            <td><span class="status">Scheduled</span></td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Track &amp; Field Meet</td>
                            <td>May 20, 2026</td>
                            <td>School Oval</td>
                            <td>Track &amp; Field</td>
                            <td><span class="status">Scheduled</span></td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Volleyball Finals</td>
                            <td>May 25, 2026</td>
                            <td>Covered Court</td>
                            <td>Volleyball</td>
                            <td><span class="status">Scheduled</span></td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Caraga Regional Athletic Games 2026</td>
                            <td>April 15, 2026</td>
                            <td>Surigao City</td>
                            <td>Track &amp; Field</td>
                            <td><span class="status">Scheduled</span></td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

        </main>
    </div>

</body>
</html>
