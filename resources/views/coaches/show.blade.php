@extends('layouts.portal')

@section('content')
    @php
        $coachInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($coach->name, 0, 2));
        $registeredAt = $coach->created_at?->format('M j, Y');
        $assignedAthletes = $coach->sport?->athletes ?? collect();
    @endphp

    <div class="module-header">
        <div>
            <h1>Coach Profile</h1>
            <p class="page-subtitle">Coach Management / Coach Details</p>
        </div>
        <a class="button button-secondary" href="{{ route('coaches.index') }}">Back to Coaches</a>
        <a class="button" href="{{ route('coaches.edit', $coach) }}">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-activity"></use></svg>
            Edit Coach
        </a>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    <section class="card profile-hero medical-hero coach-detail-hero">
        <span class="avatar avatar--lg avatar-initials coach-avatar" aria-hidden="true">{{ $coachInitials }}</span>
        <div class="medical-hero-copy">
            <span class="admin-dashboard-kicker">COACH PROFILE</span>
            <h2>{{ $coach->name }}</h2>
            <p>
                <span class="badge coach-status-{{ strtolower($coach->status) }}">{{ $coach->status }}</span>
                &middot; <span class="badge">{{ $coach->coach_type ?: 'Coach' }}</span>
                &middot; {{ $coach->sport?->name ?? 'Unassigned' }}
                @if ($registeredAt)&middot; Registered {{ $registeredAt }}@endif
            </p>
        </div>
    </section>

    <div class="grid coach-summary-grid">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
            <div class="medical-kpi-body"><small>Assigned Athletes</small><strong>{{ $assignedAthletes->count() }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
            <div class="medical-kpi-body"><small>Upcoming Events</small><strong>{{ $coach->events->count() }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
            <div class="medical-kpi-body"><small>Sport</small><strong>{{ $coach->sport?->name ?? 'Unassigned' }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-violet"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
            <div class="medical-kpi-body"><small>Coach Type</small><strong>{{ $coach->coach_type ?: 'Not set' }}</strong></div>
        </div>
    </div>

    <section class="coach-detail-grid">
        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Coach Information</h2>
                    <p class="meta">Core details for {{ $coach->name }}.</p>
                </div>
            </div>
            <div class="coach-detail-list">
                <span><strong>Full name</strong>{{ $coach->name }}</span>
                <span><strong>Specialty</strong>{{ $coach->specialty }}</span>
                <span><strong>Status</strong>{{ $coach->status }}</span>
                <span><strong>Registered on</strong>{{ $registeredAt ?? 'Not recorded' }}</span>
            </div>
        </div>

        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Contact Information</h2>
                    <p class="meta">How to reach this coach.</p>
                </div>
            </div>
            <div class="coach-detail-list">
                <span><strong>Email</strong>{{ $coach->email }}</span>
                <span><strong>Phone</strong>{{ $coach->phone ?: 'Not provided' }}</span>
            </div>
        </div>
    </section>

    <section class="card coach-info-card">
        <div class="medical-card-head">
            <div>
                <span class="admin-dashboard-kicker">COACHING ASSIGNMENT</span>
                <h2>Coaching Assignment</h2>
                <p class="meta">The sport this coach manages and how the team is organized.</p>
            </div>
        </div>
        <div class="medical-fact-grid">
            <div class="medical-fact"><span>Sport</span><strong>{{ $coach->sport?->name ?? 'Unassigned' }}</strong></div>
            <div class="medical-fact"><span>Classification</span><strong>{{ $coach->sport?->classification ?? 'Not available' }}</strong></div>
            <div class="medical-fact"><span>Coach type</span><strong>{{ $coach->coach_type ?: 'Not set' }}</strong></div>
        </div>
    </section>

    <div class="section-heading coach-section-heading">
        <div>
            <h2>Assigned Athletes</h2>
            <p class="meta">Athletes connected through the assigned sport.</p>
        </div>
        @if ($coach->sport_id)
            <a class="text-link" href="{{ route('athletes.index', ['sport_id' => $coach->sport_id]) }}">View all athletes &rarr;</a>
        @endif
    </div>

    <div class="card table-wrap coach-athlete-wrap">
        <table class="data-table coach-athlete-table">
            <thead>
                <tr>
                    <th scope="col">Athlete</th>
                    <th scope="col">Student ID</th>
                    <th scope="col">Grade</th>
                    <th scope="col">Sport</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignedAthletes as $athlete)
                    @php
                        $athleteInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($athlete->name, 0, 2));
                    @endphp
                    <tr>
                        <td>
                            <span class="coach-cell">
                                <span class="avatar avatar--sm avatar-initials coach-avatar-sm" aria-hidden="true">{{ $athleteInitials }}</span>
                                <span class="coach-cell-text">
                                    <strong>{{ $athlete->name }}</strong>
                                    <small>{{ $athlete->email ?: 'No email on file' }}</small>
                                </span>
                            </span>
                        </td>
                        <td>{{ $athlete->student_id ?: 'Not provided' }}</td>
                        <td>{{ $athlete->gradeLevel() ?: 'Not provided' }}</td>
                        <td>{{ $athlete->sport?->name ?? 'Unassigned' }}</td>
                        <td><span class="badge athlete-status-{{ strtolower($athlete->status) }}">{{ $athlete->status }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-cell">
                            <strong>No athletes assigned</strong>
                            <br>
                            <small>There are currently no athletes assigned to this coach's sport.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <section class="card coach-detail-events">
        <div class="section-heading">
            <div>
                <h2>Upcoming Events</h2>
                <p class="meta">Events assigned directly to this coach.</p>
            </div>
            @if ($coach->sport_id)
                <a class="text-link" href="{{ route('events.index', ['sport_id' => $coach->sport_id]) }}">View all &rarr;</a>
            @endif
        </div>
        @forelse ($coach->events as $event)
            <a class="related-event-row" href="{{ route('events.show', $event) }}">
                <span>
                    <strong>{{ $event->title }}</strong>
                    <small>{{ $event->starts_at->format('M j, Y g:i A') }} &middot; {{ $event->venue }}</small>
                </span>
                <span class="badge event-status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span>
            </a>
        @empty
            <p class="empty-state">No upcoming events are assigned to this coach.</p>
        @endforelse
    </section>
@endsection