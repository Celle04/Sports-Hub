@extends('layouts.portal')

@section('content')
    @php
        $initials = static function (string $name): string {
            $pieces = preg_split('/\s+/', trim($name)) ?: [];

            return mb_strtoupper(implode('', array_map(
                static fn (string $word): string => mb_substr($word, 0, 1),
                array_slice($pieces, 0, 2),
            )));
        };
        $createdAt = $sport->created_at?->format('M j, Y');
    @endphp

    <div class="module-header">
        <div>
            <h1>{{ $sport->name }}</h1>
            <p class="page-subtitle">Sports Management / Sport Details</p>
        </div>
        <a class="button button-secondary" href="{{ route('sports.index') }}">Back to Sports</a>
        <a class="button" href="{{ route('sports.edit', $sport) }}">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-activity"></use></svg>
            Edit Sport
        </a>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    <section class="card profile-hero medical-hero sport-detail-hero">
        <span class="avatar avatar--lg avatar-initials medical-hero-avatar" aria-hidden="true">{{ filled($sport->name ?? '') ? $initials($sport->name) : '?' }}</span>
        <div class="medical-hero-copy">
            <span class="admin-dashboard-kicker">SPORT PROFILE</span>
            <h2>{{ $sport->name }}</h2>
            <p>
                <span class="badge sport-status-{{ strtolower($sport->status) }}">{{ $sport->status }}</span>
                &middot; {{ $sport->classification }}
                @if ($createdAt)&middot; Added {{ $createdAt }}@endif
            </p>
        </div>
    </section>

    <section class="card sport-info-card">
        <div class="medical-card-head">
            <div>
                <span class="admin-dashboard-kicker">DETAILS</span>
                <h2>Sport Information</h2>
                <p class="meta">Basic details for {{ $sport->name }}.</p>
            </div>
        </div>
        <div class="medical-fact-grid">
            <div class="medical-fact"><span>Classification</span><strong>{{ $sport->classification }}</strong></div>
            <div class="medical-fact"><span>Status</span><strong>{{ $sport->status }}</strong></div>
            <div class="medical-fact"><span>Date created</span><strong>{{ $createdAt ?? 'Not recorded' }}</strong></div>
            <div class="medical-fact"><span>Description</span><strong>{{ $sport->description }}</strong></div>
        </div>
    </section>

    <div class="section-heading sports-section-heading">
        <div>
            <h2>Participation Summary</h2>
            <p class="meta">Registered athletes, assigned coaches and related records.</p>
        </div>
    </div>
    <div class="grid sports-detail-stats">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
            <div class="medical-kpi-body"><small>Athletes</small><strong>{{ $sport->athletes_count }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
            <div class="medical-kpi-body"><small>Coaches</small><strong>{{ $sport->coaches_count }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
            <div class="medical-kpi-body"><small>Upcoming Events</small><strong>{{ $sport->events_count }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-violet"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
            <div class="medical-kpi-body"><small>Applications</small><strong>{{ $sport->applications_count }}</strong></div>
        </div>
    </div>

    <section class="sports-detail-columns">
        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Registered Athletes</h2>
                    <p class="meta">Athletes currently assigned to this sport.</p>
                </div>
                <a class="text-link" href="{{ route('athletes.index') }}">View all &rarr;</a>
            </div>
            @forelse ($sport->athletes as $athlete)
                <div class="related-row">
                    <span>
                        <strong>{{ $athlete->name }}</strong>
                        <small>{{ $athlete->student_id ?: 'No student ID' }} &middot; {{ $sport->name }}</small>
                    </span>
                </div>
            @empty
                <p class="empty-state">No athletes are assigned to this sport.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Assigned Coaches</h2>
                    <p class="meta">Coaches linked to this sport.</p>
                </div>
                <a class="text-link" href="{{ route('coaches.index') }}">View all &rarr;</a>
            </div>
            @forelse ($sport->coaches as $coach)
                <div class="related-row">
                    <span>
                        <strong>{{ $coach->name }}</strong>
                        <small>{{ $coach->specialty }}</small>
                    </span>
                </div>
            @empty
                <p class="empty-state">No coaches are assigned to this sport.</p>
            @endforelse
        </div>
    </section>

    <section class="card sports-detail-events">
        <div class="section-heading">
            <div>
                <h2>Upcoming Events</h2>
                <p class="meta">Scheduled activities for {{ $sport->name }}.</p>
            </div>
            <a class="text-link" href="{{ route('events.index', ['sport_id' => $sport->id]) }}">View all &rarr;</a>
        </div>
        @forelse ($sport->events as $event)
            <a class="related-event-row" href="{{ route('events.show', $event) }}">
                <span>
                    <strong>{{ $event->title }}</strong>
                    <small>{{ $event->starts_at?->format('M j, Y g:i A') }} &middot; {{ $event->venue }}</small>
                </span>
                <span class="badge event-status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span>
            </a>
        @empty
            <p class="empty-state">No upcoming events for this sport.</p>
        @endforelse
    </section>

    <section class="card sports-detail-events">
        <div class="section-heading">
            <div>
                <h2>Recent Applications</h2>
                <p class="meta">Applications associated with {{ $sport->name }}.</p>
            </div>
            <a class="text-link" href="{{ route('admin.applications', ['sport_id' => $sport->id]) }}">View all &rarr;</a>
        </div>
        @forelse ($sport->applications as $application)
            <a class="related-event-row" href="{{ route('applications.show', $application) }}">
                <span>
                    <strong>{{ $application->name }}</strong>
                    <small>{{ $application->created_at?->format('M j, Y') }} &middot; {{ $application->email }}</small>
                </span>
                <span class="badge application-status-{{ strtolower(str_replace(' ', '-', $application->status)) }}">{{ $application->status }}</span>
            </a>
        @empty
            <p class="empty-state">No applications are associated with this sport.</p>
        @endforelse
    </section>
@endsection