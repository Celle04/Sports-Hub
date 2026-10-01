@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Event Scheduling</h1><p class="page-subtitle">Manage scheduled sports activities and competitions</p></div><a class="button" href="{{ route('events.create') }}">Create Event</a></div>
@if (session('success'))<div class="notice">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
<div class="grid event-summary-grid"><div class="card stat"><small>Total Events</small><strong>{{ $summary['total'] }}</strong></div><div class="card stat"><small>Scheduled</small><strong>{{ $summary['scheduled'] }}</strong></div><div class="card stat"><small>Ongoing</small><strong>{{ $summary['ongoing'] }}</strong></div><div class="card stat"><small>Completed</small><strong>{{ $summary['completed'] }}</strong></div><div class="card stat"><small>Cancelled</small><strong>{{ $summary['cancelled'] }}</strong></div></div>
<section class="card event-upcoming-panel"><div class="section-heading"><div><h2>Upcoming Events</h2><p class="meta">Scheduled activities starting today or later.</p></div></div><div class="upcoming-event-grid">@forelse ($upcomingEvents as $event)<a class="upcoming-event" href="{{ route('events.show', $event) }}"><strong>{{ $event->title }}</strong><span>{{ $event->starts_at->format('M j, Y') }} &middot; {{ $event->starts_at->format('g:i A') }}</span><small>{{ $event->venue }} &middot; {{ $event->sport?->name ?? 'All sports' }}</small></a>@empty<p class="empty-state">No upcoming events.</p>@endforelse</div></section>
<section class="card event-filter-panel"><div class="section-heading"><div><h2>Find Events</h2><p class="meta">Search and filter the event schedule.</p></div></div><form method="GET" action="{{ route('events.index') }}" class="event-filters"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search events..." aria-label="Search events"><select class="form-control" name="sport_id"><option value="">All Sports</option>@foreach ($sports as $sport)<option value="{{ $sport->id }}" @selected((string) request('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>@endforeach</select><select class="form-control" name="status"><option value="">All Statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select><select class="form-control" name="event_type"><option value="">All Event Types</option>@foreach ($eventTypes as $eventType)<option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>{{ $eventType }}</option>@endforeach</select><select class="form-control" name="venue"><option value="">All Venues</option>@foreach ($venues as $venue)<option value="{{ $venue }}" @selected(request('venue') === $venue)>{{ $venue }}</option>@endforeach</select><input class="form-control" type="date" name="date" value="{{ request('date') }}" aria-label="Filter by date"><button class="button" type="submit">Search</button><a class="button button-muted" href="{{ route('events.index') }}">Reset</a></form></section>
<div class="grid grid-2">
    @forelse ($events as $event)
        <article class="card event-card">
            <div class="event-card-heading"><div><h3>{{ $event->title }}</h3><span class="badge event-status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span></div><span class="event-type-label">{{ $event->event_type }}</span></div>
            <p class="event-sport">{{ $event->sport?->name ?? 'All sports' }} <span>&middot;</span> {{ $event->event_type }}</p>
            <div class="event-details-list"><span><strong>Date</strong>{{ $event->starts_at->format('F j, Y') }}</span><span><strong>Time</strong>{{ $event->starts_at->format('g:i A') }} - {{ $event->ends_at->format('g:i A') }}</span><span><strong>Venue</strong>{{ $event->venue }}</span><span><strong>Coach</strong>{{ $event->coach?->name ?? 'Unassigned' }}</span><span><strong>Team</strong>{{ $event->team_name ?: 'Not assigned' }}</span><span><strong>Participants</strong>{{ $event->attendance_records_count }}{{ $event->max_participants ? ' / '.$event->max_participants : '' }}</span></div>
            @if ($event->max_participants && $event->attendance_records_count >= $event->max_participants)<p class="capacity-warning">Capacity reached</p>@endif
            @if ($event->description)<p class="event-description">{{ $event->description }}</p>@endif
            <div class="event-actions"><a class="button" href="{{ route('events.show', $event) }}">View Details</a><a class="button button-secondary" href="{{ route('admin.attendance', ['event_id' => $event->id]) }}">Attendance</a><a class="button button-secondary" href="{{ route('events.edit', $event) }}">Edit</a><form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Are you sure you want to delete this event?');">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div>
        </article>
    @empty
        <div class="card event-empty-state"><h2>{{ request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']) ? 'No events match your search.' : 'No events scheduled yet.' }}</h2><p>{{ request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']) ? 'Try adjusting your filters.' : 'Create an event to begin managing sports activities.' }}</p>
            @if (request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']))<a class="button button-muted" href="{{ route('events.index') }}">Reset Filters</a>@else<a class="button" href="{{ route('events.create') }}">+ Create Event</a>@endif
        </div>
    @endforelse
</div>
@endsection{{-- <!DOCTYPE html>
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
                <button class="button">&#43;&nbsp; Create</button>
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
</html> --}}
