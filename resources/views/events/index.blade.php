@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Event Scheduling</h1><p class="page-subtitle">Manage scheduled sports activities and competitions</p></div><a class="button" href="{{ route('events.create') }}">Create Event</a></div>

@if (session('success'))<div class="notice">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif

<div class="grid event-summary-grid">
    <div class="card medical-kpi"><span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span><div class="medical-kpi-body"><small>Total Events</small><strong>{{ $summary['total'] }}</strong></div></div>
    <div class="card medical-kpi"><span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span><div class="medical-kpi-body"><small>Scheduled</small><strong>{{ $summary['scheduled'] }}</strong></div></div>
    <div class="card medical-kpi"><span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span><div class="medical-kpi-body"><small>Ongoing</small><strong>{{ $summary['ongoing'] }}</strong></div></div>
    <div class="card medical-kpi"><span class="medical-kpi-icon tone-green"><svg aria-hidden="true"><use href="#icon-medical"></use></svg></span><div class="medical-kpi-body"><small>Completed</small><strong>{{ $summary['completed'] }}</strong></div></div>
    <div class="card medical-kpi"><span class="medical-kpi-icon tone-danger"><svg aria-hidden="true"><use href="#icon-bell"></use></svg></span><div class="medical-kpi-body"><small>Cancelled</small><strong>{{ $summary['cancelled'] }}</strong></div></div>
</div>

<section class="card event-upcoming-panel">
    <div class="medical-card-head">
        <div><span class="admin-dashboard-kicker">NEXT UP</span><h2>Upcoming Events</h2><p>Scheduled activities starting today or later.</p></div>
        <div class="event-panel-actions"><a class="text-link" href="{{ route('events.create') }}">Schedule event</a><span class="medical-count-chip">{{ $upcomingEvents->count() }}</span></div>
    </div>
    <div class="upcoming-event-grid">
        @forelse ($upcomingEvents as $event)
            <a class="upcoming-event" href="{{ route('events.show', $event) }}">
                @php $tone = $event->status === 'Ongoing' ? 'tone-blue' : 'tone-gold'; @endphp
                <i class="medical-kpi-icon {{ $tone }}" aria-hidden="true"><svg><use href="#icon-calendar"></use></svg></i>
                <span class="upcoming-event-copy"><strong>{{ $event->title }}</strong><small>{{ $event->starts_at->format('M j, Y') }} &middot; {{ $event->starts_at->format('g:i A') }}</small><small>{{ $event->venue }} &middot; {{ $event->sport?->name ?? 'All sports' }}</small></span>
            </a>
        @empty
            <p class="empty-state">No upcoming events.</p>
        @endforelse
    </div>
</section>

<section class="card event-filter-panel">
    <div class="medical-card-head">
        <div><span class="admin-dashboard-kicker">FILTER</span><h2>Find Events</h2><p>Search and filter the event schedule.</p></div>
    </div>
    <form method="GET" action="{{ route('events.index') }}" class="event-filters">
        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search events..." aria-label="Search events">
        <select class="form-control" name="sport_id"><option value="">All Sports</option>@foreach ($sports as $sport)<option value="{{ $sport->id }}" @selected((string) request('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>@endforeach</select>
        <select class="form-control" name="status"><option value="">All Statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
        <select class="form-control" name="event_type"><option value="">All Event Types</option>@foreach ($eventTypes as $eventType)<option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>{{ $eventType }}</option>@endforeach</select>
        <select class="form-control" name="venue"><option value="">All Venues</option>@foreach ($venues as $venue)<option value="{{ $venue }}" @selected(request('venue') === $venue)>{{ $venue }}</option>@endforeach</select>
        <input class="form-control" type="date" name="date" value="{{ request('date') }}" aria-label="Filter by date">
        <button class="button" type="submit">Search</button>
        <a class="button button-muted" href="{{ route('events.index') }}">Reset</a>
    </form>
</section>

<div class="grid grid-2">
    @forelse ($events as $event)
        <article class="card event-card">
            <div class="event-card-top">
                @php $cardTone = $event->status === 'Cancelled' ? 'tone-danger' : ($event->status === 'Completed' ? 'tone-green' : ($event->status === 'Ongoing' ? 'tone-blue' : 'tone-gold')); @endphp
                <span class="medical-kpi-icon {{ $cardTone }}"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
                <div class="event-card-heading"><div><h3>{{ $event->title }}</h3><span class="badge event-status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span></div><span class="event-type-label">{{ $event->event_type }}</span></div>
                <a class="text-link event-card-open" href="{{ route('events.show', $event) }}">Open</a>
            </div>
            <p class="event-sport">{{ $event->sport?->name ?? 'All sports' }} <span>&middot;</span> {{ $event->event_type }}</p>
            <div class="event-details-list"><span><strong>Date</strong>{{ $event->starts_at->format('F j, Y') }}</span><span><strong>Time</strong>{{ $event->starts_at->format('g:i A') }} - {{ $event->ends_at->format('g:i A') }}</span><span><strong>Venue</strong>{{ $event->venue }}</span><span><strong>Coach</strong>{{ $event->coach?->name ?? 'Unassigned' }}</span><span><strong>Team</strong>{{ $event->team_name ?: 'Not assigned' }}</span><span><strong>Participants</strong>{{ $event->attendance_records_count }}{{ $event->max_participants ? ' / '.$event->max_participants : '' }}</span></div>
            @if ($event->max_participants && $event->attendance_records_count >= $event->max_participants)<p class="capacity-warning">Capacity reached</p>@endif
            @if ($event->description)<p class="event-description">{{ $event->description }}</p>@endif
            <div class="event-actions"><a class="button" href="{{ route('events.show', $event) }}">View Details</a><a class="row-action" href="{{ route('admin.attendance', ['event_id' => $event->id]) }}">Attendance</a><a class="row-action" href="{{ route('events.edit', $event) }}">Edit</a><button class="row-action row-action-danger" type="button" data-confirm-dialog data-confirm-title="Delete Event?" data-confirm-message="Are you sure you want to delete this event? This action cannot be undone." data-confirm-label="Delete Event" data-confirm-method="DELETE" data-confirm-url="{{ route('events.destroy', $event) }}">Delete</button></div>
        </article>
    @empty
        <div class="card event-empty-state">
            @if (request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']))
                <i class="medical-kpi-icon tone-violet" aria-hidden="true"><svg><use href="#icon-search"></use></svg></i>
            @else
                <i class="medical-kpi-icon tone-violet" aria-hidden="true"><svg><use href="#icon-calendar"></use></svg></i>
            @endif
            <h2>{{ request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']) ? 'No events match your search.' : 'No events scheduled yet.' }}</h2><p>{{ request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']) ? 'Try adjusting your filters.' : 'Create an event to begin managing sports activities.' }}</p>
            @if (request()->hasAny(['search', 'sport_id', 'status', 'event_type', 'venue', 'date']))<a class="button button-muted" href="{{ route('events.index') }}">Reset Filters</a>@else<a class="button" href="{{ route('events.create') }}">+ Create Event</a>@endif
        </div>
    @endforelse
</div>
@endsection