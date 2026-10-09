@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Event Details</h1><p class="page-subtitle">Review the complete event record</p></div><div class="event-header-actions"><a class="button button-secondary" href="{{ route('events.index') }}">Back</a><a class="button" href="{{ route('events.edit', $event) }}">Edit Event</a></div></div>

<div class="card event-detail-card">
    <div class="profile-hero event-hero">
        <span class="medical-kpi-icon event-hero-icon"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
        <div class="event-hero-copy">
            <span class="admin-dashboard-kicker">EVENT RECORD</span>
            <h2>{{ $event->title }}</h2>
            <p>{{ $event->sport?->name ?? 'All sports' }} &middot; {{ $event->event_type }} &middot; <span class="badge event-status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span></p>
        </div>
        <div class="event-hero-actions"><a class="event-hero-back" href="{{ route('admin.attendance', ['event_id' => $event->id]) }}">Attendance</a><a class="event-hero-back" href="{{ route('events.edit', $event) }}">Edit</a></div>
    </div>

    <div class="event-detail-facts">
        <div class="medical-fact"><span>Sport</span><strong>{{ $event->sport?->name ?? 'All sports' }}</strong></div>
        <div class="medical-fact"><span>Event type</span><strong>{{ $event->event_type }}</strong></div>
        <div class="medical-fact"><span>Date</span><strong>{{ $event->starts_at->format('F j, Y') }}</strong></div>
        <div class="medical-fact"><span>Time</span><strong>{{ $event->starts_at->format('g:i A') }} - {{ $event->ends_at->format('g:i A') }}</strong></div>
        <div class="medical-fact"><span>Venue</span><strong>{{ $event->venue }}</strong></div>
        <div class="medical-fact"><span>Coach</span><strong>{{ $event->coach?->name ?? 'Unassigned' }}</strong></div>
        <div class="medical-fact"><span>Team</span><strong>{{ $event->team_name ?: 'Not assigned' }}</strong></div>
        <div class="medical-fact"><span>Participants</span><strong>{{ $event->attendance_records_count }}{{ $event->max_participants ? ' / '.$event->max_participants : '' }}</strong></div>
    </div>

    @if ($event->description)<div class="event-detail-copy"><strong>Description</strong><p>{{ $event->description }}</p></div>@endif
    @if ($event->notes)<div class="event-detail-copy"><strong>Notes</strong><p>{{ $event->notes }}</p></div>@endif

    <div class="event-actions"><a class="button" href="{{ route('admin.attendance', ['event_id' => $event->id]) }}">View Attendance</a><a class="button button-secondary" href="{{ route('events.edit', $event) }}">Edit Event</a></div>
</div>
@endsection