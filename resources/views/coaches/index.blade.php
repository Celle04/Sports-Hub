<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Management &ndash; Sports Activity Hub</title>
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
                    <small>Administrator</small>
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
                <a class="active" href="{{ route('coaches.index') }}">
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
                    <h1>Coach Management</h1>
                    <p class="sub">Manage coaching staff and assignments</p>
                </div>
                <button class="button">&#43;&nbsp; Add Coach</button>
            </div>

            <section class="panel">
                <h2>Coaching Directory</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Assigned Sport</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Coach Roberto Santos</td>
                            <td>Basketball</td>
                            <td>Basketball</td>
                            <td>0917-123-4567</td>
                            <td>roberto.santos@snhs.edu</td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Coach Elena Torres</td>
                            <td>Volleyball</td>
                            <td>Volleyball</td>
                            <td>0918-325-7621</td>
                            <td>elena.torres@snhs.edu</td>
                            <td>
                                <span class="actions">
                                    <a href="#">&#9998;</a>
                                    <a href="#">&#128465;</a>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Coach Mark Villanueva</td>
                            <td>Track &amp; Field</td>
                            <td>Track &amp; Field</td>
                            <td>0917-995-3214</td>
                            <td>mark.villanueva@snhs.edu</td>
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
