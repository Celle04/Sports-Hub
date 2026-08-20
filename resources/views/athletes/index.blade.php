<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athlete Management &ndash; Sports Activity Hub</title>
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
                <a class="active" href="{{ route('athletes.index') }}">
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

            <div class="heading">
                <div>
                    <h1>Athlete Management</h1>
                    <p class="sub">Register and manage student athletes</p>
                </div>
                <button class="button">&#43;&nbsp; Register Athlete</button>
            </div>

            <section class="panel">
                <h2>Registered Athletes</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Grade Level</th>
                            <th>Sport</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Dela Cruz</td>
                            <td>16</td>
                            <td>Male</td>
                            <td>Grade 11</td>
                            <td>Basketball</td>
                            <td><span class="status">Active</span></td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Maria Santos</td>
                            <td>15</td>
                            <td>Female</td>
                            <td>Grade 10</td>
                            <td>Volleyball</td>
                            <td><span class="status">Active</span></td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Kevin Morales</td>
                            <td>17</td>
                            <td>Male</td>
                            <td>Grade 12</td>
                            <td>Track &amp; Field</td>
                            <td><span class="status">Active</span></td>
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
