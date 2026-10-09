@extends('layouts.portal')

@section('content')
    @php
        $latestApplication = $athlete->applications->sortByDesc('created_at')->first();
        $latestMedical = $athlete->medicalRecords->sortByDesc('examination_date')->first();
        $eligibility = $latestMedical?->medical_status === 'Cleared' ? 'Eligible' : ($latestApplication?->status === 'Rejected' ? 'Not Eligible' : 'Pending');
        $attendanceRecords = $athlete->attendanceRecords;
        $presentCount = $attendanceRecords->where('status', 'Present')->count();
        $absentCount = $attendanceRecords->where('status', 'Absent')->count();
        $lateCount = $attendanceRecords->where('status', 'Late')->count();
        $attendanceTotal = $attendanceRecords->count();
        $attendanceRate = $attendanceTotal > 0 ? round($presentCount / $attendanceTotal * 100) : 0;
        $events = $attendanceRecords->map(fn ($record) => $record->event)->filter()->unique('id')->sortByDesc('starts_at');
        $coaches = $athlete->sport?->coaches ?? collect();
        $createdAt = $athlete->created_at?->format('M j, Y');
    @endphp

    <div class="module-header">
        <div>
            <h1>Athlete Profile</h1>
            <p class="page-subtitle">Athlete Management / Athlete Details</p>
        </div>
        <a class="button button-secondary" href="{{ route('athletes.index') }}">Back to Athletes</a>
        <a class="button" href="{{ route('athletes.edit', $athlete) }}">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-activity"></use></svg>
            Edit Athlete
        </a>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    <section class="card profile-hero medical-hero athlete-detail-hero">
        <x-avatar :user="$athlete" size="lg" class="athlete-avatar" />
        <div class="medical-hero-copy">
            <span class="admin-dashboard-kicker">ATHLETE PROFILE</span>
            <h2>{{ $athlete->name }}</h2>
            <p>
                <span class="badge athlete-status-{{ strtolower($athlete->status) }}">{{ $athlete->status }}</span>
                &middot; <span class="badge eligibility-{{ strtolower(str_replace(' ', '-', $eligibility)) }}">{{ $eligibility }}</span>
                &middot; {{ $athlete->sport?->name ?? 'Unassigned' }}
                @if ($createdAt)&middot; Joined {{ $createdAt }}@endif
            </p>
        </div>
    </section>

    <section class="card">
        <div class="medical-card-head">
            <div>
                <span class="admin-dashboard-kicker">DETAILS</span>
                <h2>Athlete Information</h2>
                <p class="meta">Account and personal details for {{ $athlete->name }}.</p>
            </div>
        </div>
        <div class="medical-fact-grid">
            <div class="medical-fact"><span>Full name</span><strong>{{ $athlete->name }}</strong></div>
            <div class="medical-fact"><span>Student ID</span><strong>{{ $athlete->student_id ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Grade</span><strong>{{ $athlete->gradeLevel() ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Username</span><strong>{{ $athlete->username ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Email</span><strong>{{ $athlete->email }}</strong></div>
            <div class="medical-fact"><span>Gender</span><strong>{{ $latestApplication?->gender ?: 'Not provided' }}</strong></div>
        </div>
    </section>

    <div class="section-heading athlete-section-heading">
        <div>
            <h2>Participation Summary</h2>
            <p class="meta">Sport, coaching and related records for this athlete.</p>
        </div>
    </div>
    <div class="grid athlete-profile-stats">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
            <div class="medical-kpi-body"><small>Sport</small><strong>{{ $athlete->sport?->name ?? 'Unassigned' }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
            <div class="medical-kpi-body"><small>Coaches</small><strong>{{ $coaches->count() }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
            <div class="medical-kpi-body"><small>Related Events</small><strong>{{ $events->count() }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-violet"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
            <div class="medical-kpi-body"><small>Applications</small><strong>{{ $athlete->applications->count() }}</strong></div>
        </div>
    </div>

    <section class="athlete-detail-grid">
        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Sport Participation</h2>
                    <p class="meta">Sport assignment and assigned coaches.</p>
                </div>
            </div>
            @if ($athlete->sport)
                <div class="athlete-detail-list">
                    <span><strong>Sport</strong>{{ $athlete->sport->name }}</span>
                    <span><strong>Classification</strong>{{ $athlete->sport->classification ?? 'Not available' }}</span>
                </div>
                @forelse ($coaches as $coach)
                    <div class="related-row">
                        <span>
                            <strong>{{ $coach->name }}</strong>
                            <small>{{ $coach->specialty ?: 'Coach' }}</small>
                        </span>
                    </div>
                @empty
                    <p class="empty-state">No coach is assigned to this sport.</p>
                @endforelse
            @else
                <p class="empty-state">This athlete is not assigned to a sport.</p>
            @endif
        </div>

        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Emergency Contact</h2>
                    <p class="meta">Who to call in an emergency.</p>
                </div>
            </div>
            <div class="athlete-detail-list">
                <span><strong>Contact</strong>{{ $athlete->emergency_contact_name ?: 'Not provided' }}</span>
                <span><strong>Relationship</strong>{{ $athlete->emergency_contact_relationship ?: 'Not provided' }}</span>
                <span><strong>Contact number</strong>{{ $athlete->emergency_contact_phone ?: 'Not provided' }}</span>
            </div>
            @unless ($athlete->hasEmergencyContact())
                <p class="empty-state">No usable emergency contact recorded.</p>
            @endunless
        </div>
    </section>

    <div class="section-heading athlete-section-heading">
        <div>
            <h2>Attendance Summary</h2>
            <p class="meta">Attendance across every event this athlete is recorded on.</p>
        </div>
    </div>
    <div class="grid athlete-profile-stats">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
            <div class="medical-kpi-body"><small>Total Records</small><strong>{{ $attendanceTotal }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-green"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span>
            <div class="medical-kpi-body"><small>Present</small><strong>{{ $presentCount }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
            <div class="medical-kpi-body"><small>Absent</small><strong>{{ $absentCount }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
            <div class="medical-kpi-body"><small>Attendance Rate</small><strong>{{ $attendanceRate }}%</strong></div>
        </div>
    </div>

    <section class="athlete-detail-grid">
        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Eligibility and Medical</h2>
                    <p class="meta">Latest medical clearance and document checks.</p>
                </div>
                <a class="text-link" href="{{ route('admin.medical', ['search' => $athlete->name]) }}">View medical &rarr;</a>
            </div>
            <div class="athlete-detail-list">
                <span><strong>Medical clearance</strong>{{ $latestMedical?->medical_status ?? 'Pending' }}</span>
                <span><strong>Birth certificate</strong>{{ $latestApplication?->birth_document_status ?? ($latestApplication?->birth_certificate_path ? 'Submitted' : 'Missing') }}</span>
                <span><strong>Parent consent</strong>{{ $latestApplication?->consent_document_status ?? ($latestApplication?->parent_consent_path ? 'Submitted' : 'Missing') }}</span>
                <span><strong>Eligibility status</strong>{{ $eligibility }}</span>
            </div>
            @if ($latestMedical)
                <div class="review-note">
                    <strong>Medical findings</strong>
                    <p>{{ $latestMedical->findings ?: 'No findings recorded.' }}</p>
                    <strong>Restrictions</strong>
                    <p>{{ $latestMedical->restrictions ?: 'None recorded.' }}</p>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="section-heading">
                <div>
                    <h2>Application History</h2>
                    <p class="meta">Applications linked to this athlete.</p>
                </div>
                <a class="text-link" href="{{ route('admin.applications', ['search' => $athlete->student_id ?: $athlete->name]) }}">View all &rarr;</a>
            </div>
            @forelse ($athlete->applications->sortByDesc('created_at') as $application)
                <a class="related-event-row" href="{{ route('applications.show', $application) }}">
                    <span>
                        <strong>{{ $application->grade ?: 'Application' }}</strong>
                        <small>{{ $application->created_at?->format('M j, Y') }} &middot; {{ $application->sport ?: 'Unassigned' }}</small>
                    </span>
                    <span class="badge application-status-{{ strtolower(str_replace(' ', '-', $application->status)) }}">{{ $application->status }}</span>
                </a>
            @empty
                <p class="empty-state">No applications are linked to this athlete.</p>
            @endforelse
        </div>
    </section>

    <section class="card athlete-detail-events">
        <div class="section-heading">
            <div>
                <h2>Recent Events</h2>
                <p class="meta">Events this athlete has been recorded on.</p>
            </div>
            <a class="text-link" href="{{ route('events.index', ['sport_id' => $athlete->sport_id]) }}">View all &rarr;</a>
        </div>
        @forelse ($events->take(6) as $event)
            <a class="related-event-row" href="{{ route('events.show', $event) }}">
                <span>
                    <strong>{{ $event->title }}</strong>
                    <small>{{ $event->starts_at?->format('M j, Y g:i A') }} &middot; {{ $event->venue }}</small>
                </span>
                <span class="badge event-status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span>
            </a>
        @empty
            <p class="empty-state">No event participation recorded.</p>
        @endforelse
    </section>
@endsection