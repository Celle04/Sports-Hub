@extends('layouts.portal')

@section('content')
<div class="module-header">
    <div>
        <h1>Event Details</h1>
        <p class="page-subtitle">Review the complete event record</p>
    </div>
    <div class="event-header-actions">
        <a class="button button-secondary button-icon-only" href="{{ route('events.index') }}" aria-label="Back to Events" title="Back to Events"><svg class="button-icon" aria-hidden="true"><use href="#icon-arrow-left"></use></svg></a>
        <a class="button" href="{{ route('events.edit', $event) }}">Edit Event</a>
    </div>
</div>

<div class="card event-detail-card">
    <div class="event-banner">
        <span class="event-banner-icon" aria-hidden="true"><svg><use href="#icon-calendar"></use></svg></span>
        <div class="event-banner-copy">
            <span class="admin-dashboard-kicker">Event Record</span>
            <h2>{{ $event->title }}</h2>
            <p>
                <span class="event-banner-meta">{{ $event->sport?->name ?? 'All sports' }} &middot; {{ $event->event_type }}</span>
                <span class="badge">{{ $event->status }}</span>
            </p>
        </div>
        <div class="event-banner-actions">
            <a class="button" href="{{ route('admin.attendance', ['event_id' => $event->id]) }}">View Attendance</a>
        </div>
    </div>

    <div class="event-detail-facts">
        <div class="medical-fact"><span>Sport</span><strong>{{ $event->sport?->name ?? 'Not assigned' }}</strong></div>
        <div class="medical-fact"><span>Event type</span><strong>{{ $event->event_type }}</strong></div>
        <div class="medical-fact"><span>Date</span><strong>{{ $event->starts_at->format('F j, Y') }}</strong></div>
        <div class="medical-fact"><span>Time</span><strong>{{ $event->starts_at->format('g:i A') }} - {{ $event->ends_at->format('g:i A') }}</strong></div>
        <div class="medical-fact"><span>Venue</span><strong>{{ $event->venue }}</strong></div>
        <div class="medical-fact"><span>Coach</span><strong>{{ $event->coach?->name ?? 'Not assigned' }}</strong></div>
        <div class="medical-fact"><span>Team</span><strong>{{ $event->team_name ?: 'Not assigned' }}</strong></div>
        <div class="medical-fact"><span>Participants</span><strong>{{ $event->attendance_records_count }}{{ $event->max_participants ? ' / '.$event->max_participants : '' }}</strong></div>
    </div>

    @if ($event->description || $event->notes)
    <div class="event-detail-copy">
        @if ($event->description)
            <strong>Description</strong>
            <p>{{ $event->description }}</p>
        @endif
        @if ($event->notes)
            <strong>Notes</strong>
            <p>{{ $event->notes }}</p>
        @endif
    </div>
    @endif

    @if ($event->attendance_records_count > 0)
    <div class="event-detail-copy">
        <strong>Attendance</strong>
        <p>{{ $event->attendance_records_count }} record{{ $event->attendance_records_count === 1 ? '' : 's' }} on file. <a class="text-link" href="{{ route('admin.attendance', ['event_id' => $event->id]) }}">View all attendance records</a></p>
    </div>
    @endif

    <div class="event-actions" style="margin-top: 30px;">
        <button class="button button-danger" type="button" data-confirm-dialog data-confirm-title="Delete Event?" data-confirm-message="Are you sure you want to delete this event? This action cannot be undone." data-confirm-label="Delete Event" data-confirm-method="POST" data-confirm-url="{{ route('events.destroy', $event) }}">Delete Event</button>
    </div>
</div>
@endsection
