<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports Management &ndash; SportsHub</title>
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
                    <div class="brand-role">Administrator Panel</div>
                </div>
            </div>

            <nav class="nav" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}">
                    <span class="icon">&#127968;</span>Dashboard
                </a>
                <a class="active" href="{{ route('sports.index') }}">
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

            <div class="page-heading">
                <div>
                    <h1>Sports Management</h1>
                    <p class="subtitle">Manage sports categories and classifications</p>
                </div>
                <a class="add-button" href="#">&#43;&nbsp; Add Sport</a>
            </div>

            <section class="panel" aria-label="Sports list">
                <h2 class="panel-title">Sports List</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Sport Name</th>
                            <th>Classification</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basketball</td>
                            <td>Team Sport</td>
                            <td>5 vs 5 indoor court game</td>
                            <td>
                                <span class="actions">
                                    <a href="#" aria-label="Edit Basketball">&#9998;</a>
                                    <a href="#" aria-label="Delete Basketball">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Volleyball</td>
                            <td>Team Sport</td>
                            <td>6v6 net sport</td>
                            <td>
                                <span class="actions">
                                    <a href="#" aria-label="Edit Volleyball">&#9998;</a>
                                    <a href="#" aria-label="Delete Volleyball">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Track &amp; Field</td>
                            <td>Individual</td>
                            <td>Running and field events</td>
                            <td>
                                <span class="actions">
                                    <a href="#" aria-label="Edit Track and Field">&#9998;</a>
                                    <a href="#" aria-label="Delete Track and Field">&#128465;</a>
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
