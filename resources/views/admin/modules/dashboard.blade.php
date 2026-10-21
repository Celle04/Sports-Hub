@php
    $largestSportAthletes = max(1, (int) $athletesBySport->max('athletes_count'));
    $largestSportAchievements = max(1, (int) $achievementsBySport->max('total'));

    /*
     * Status -> tone maps keep every badge in the dashboard consistent with the
     * shared status palette used by the rest of the portal.
     */
    $eventTone = fn (string $status) => match ($status) {
        'Scheduled' => 'info',
        'Ongoing' => 'success',
        'Completed' => 'neutral',
        'Cancelled' => 'danger',
        'Postponed' => 'warning',
        default => 'neutral',
    };
    $applicationTone = fn (string $status) => match ($status) {
        'Pending' => 'warning',
        'Under Review' => 'info',
        'Documents Required' => 'info',
        'Approved' => 'success',
        'Rejected' => 'danger',
        'Waitlisted' => 'neutral',
        default => 'neutral',
    };
    $medicalTone = fn (string $status) => match ($status) {
        'Cleared' => 'success',
        'Pending' => 'warning',
        'Not Cleared' => 'danger',
        'Restricted' => 'violet',
        default => 'neutral',
    };
@endphp

<div class="hub-dashboard">

    {{-- ====================================================================
         PAGE HEADER
         ==================================================================== --}}
    <header class="hub-hero">
        <div class="hub-hero-copy">
            <span class="hub-hero-status"><span class="hub-hero-status-dot" aria-hidden="true"></span> Live system &middot; {{ now()->format('l, F j, Y') }}</span>
            <h1 class="hub-hero-title">SportsHub Command <span>Center</span></h1>
            <p class="hub-hero-subtitle">Monitor athletes, sports activities, attendance, achievements, and upcoming events in one place.</p>
        </div>
        <div class="hub-hero-actions">
            <a class="hub-button hub-button--primary" href="{{ route('events.create') }}">
                <svg aria-hidden="true"><use href="#icon-plus"></use></svg>Create Event
            </a>
            <a class="hub-button" href="{{ route('athletes.index') }}">
                <svg aria-hidden="true"><use href="#icon-users"></use></svg>Manage Athletes
            </a>
            <a class="hub-button" href="{{ route('admin.attendance') }}">
                <svg aria-hidden="true"><use href="#icon-clipboard"></use></svg>Take Attendance
            </a>
            <a class="hub-button" href="{{ route('reports.index') }}">
                <svg aria-hidden="true"><use href="#icon-chart"></use></svg>View Reports
            </a>
        </div>
    </header>

    {{-- ====================================================================
         NEEDS ATTENTION
         ==================================================================== --}}
    @if (($attentionItems ?? collect())->isNotEmpty())
        <section class="hub-attention" aria-label="Items that need attention">
            <div class="hub-attention-head">
                <span class="hub-panel-kicker">Action required</span>
                <h2 class="hub-attention-title">Needs your attention</h2>
            </div>
            <ul class="hub-attention-list">
                @foreach ($attentionItems as $item)
                    <li>
                        <a class="hub-attention-item hub-attention-item--{{ $item['tone'] }}" href="{{ route($item['route']) }}">
                            <span class="hub-attention-icon" aria-hidden="true">
                                <svg aria-hidden="true"><use href="#icon-{{ $item['tone'] === 'danger' ? 'medical' : 'bell' }}"></use></svg>
                            </span>
                            <span class="hub-attention-message">{{ $item['message'] }}</span>
                            <span class="hub-attention-action">{{ $item['action'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ====================================================================
         KEY METRICS
         ==================================================================== --}}
    <section class="hub-metrics" aria-label="Key metrics">
        @foreach ($metrics as $metric)
            <a class="hub-metric" href="{{ route($metric['route']) }}">
                <span class="hub-metric-head">
                    <span class="hub-metric-label">{{ $metric['label'] }}</span>
                    <span class="hub-metric-icon hub-tone-{{ $metric['tone'] }}" aria-hidden="true">
                        <svg aria-hidden="true"><use href="#icon-{{ $metric['icon'] }}"></use></svg>
                    </span>
                </span>
                <strong class="hub-metric-value">{{ $metric['value'] }}</strong>
                <span class="hub-metric-foot">
                    <span class="hub-metric-hint">{{ $metric['hint'] }}</span>
                    <span class="hub-metric-action">{{ $metric['action'] }}</span>
                </span>
            </a>
        @endforeach
    </section>

    {{-- ====================================================================
         MAIN COLUMN + SUPPORTING COLUMN
         ==================================================================== --}}
    <div class="hub-columns">

        {{-- ============================ MAIN ============================ --}}
        <div class="hub-column hub-column--main">

            {{-- Upcoming events --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">What&rsquo;s next</span>
                        <h2 class="hub-panel-title">Upcoming Events</h2>
                        <p class="hub-panel-sub">{{ $upcomingEvents->count() }} scheduled event{{ $upcomingEvents->count() === 1 ? '' : 's' }} on record</p>
                    </div>
                    <a class="hub-panel-link" href="{{ route('events.index') }}">All events</a>
                </header>
                <div class="hub-panel-body hub-panel-body--flush">
                    @forelse ($upcomingEvents as $event)
                        <a class="hub-row" href="{{ route('events.show', $event) }}">
                            <span class="hub-row-date" aria-hidden="true">
                                <em>{{ $event->starts_at->format('M') }}</em>
                                <strong>{{ $event->starts_at->format('j') }}</strong>
                            </span>
                            <span class="hub-row-main">
                                <strong>{{ $event->title }}</strong>
                                <small>{{ $event->starts_at->format('l, g:i A') }} &middot; {{ $event->sport?->name ?? 'All sports' }} &middot; {{ $event->venue ?: 'Venue not set' }}</small>
                            </span>
                            <span class="hub-row-side">
                                <span class="hub-badge hub-badge--{{ $eventTone($event->status) }}">{{ $event->status }}</span>
                                <small>{{ $event->starts_at->diffForHumans() }}</small>
                            </span>
                        </a>
                    @empty
                        <p class="hub-empty">No upcoming events are scheduled.</p>
                    @endforelse
                </div>
            </section>

            {{-- Sports performance --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">School-wide overview</span>
                        <h2 class="hub-panel-title">Sports Performance</h2>
                        <p class="hub-panel-sub">Live counts across {{ $sportsStats['sports'] }} program{{ $sportsStats['sports'] === 1 ? '' : 's' }} and every recorded achievement</p>
                    </div>
                    <div class="hub-panel-actions">
                        <a class="hub-panel-link" href="{{ route('admin.achievements') }}">View All Achievements</a>
                        <a class="hub-button hub-button--primary hub-button--sm" href="{{ route('admin.achievements.certificate.generate') }}">Generate Certificate</a>
                    </div>
                </header>
                <div class="hub-panel-body">
                    <div class="hub-tiles">
                        <div class="hub-tile">
                            <small>Total Achievements</small>
                            <strong>{{ $sportsStats['achievements'] }}</strong>
                        </div>
                        <div class="hub-tile hub-tile--gold">
                            <small>Gold Medals</small>
                            <strong>{{ $sportsStats['gold'] }}</strong>
                        </div>
                        <div class="hub-tile hub-tile--silver">
                            <small>Silver Medals</small>
                            <strong>{{ $sportsStats['silver'] }}</strong>
                        </div>
                        <div class="hub-tile hub-tile--bronze">
                            <small>Bronze Medals</small>
                            <strong>{{ $sportsStats['bronze'] }}</strong>
                        </div>
                    </div>

                    <div class="hub-charts">
                        <div class="hub-chart">
                            <h3 class="hub-chart-title">Athletes by Sport</h3>
                            <p class="hub-chart-sub">{{ $athletesBySport->sum('athletes_count') }} athlete{{ $athletesBySport->sum('athletes_count') === 1 ? '' : 's' }} assigned to {{ $athletesBySport->count() }} program{{ $athletesBySport->count() === 1 ? '' : 's' }}</p>
                            @forelse ($athletesBySport as $sport)
                                <div class="hub-bar">
                                    <div class="hub-bar-label"><span>{{ $sport->name }}</span><strong>{{ $sport->athletes_count }}</strong></div>
                                    <div class="progress-track" role="progressbar" aria-label="{{ $sport->name }} athlete count" aria-valuemin="0" aria-valuemax="{{ $largestSportAthletes }}" aria-valuenow="{{ $sport->athletes_count }}"><span style="width: {{ round($sport->athletes_count / $largestSportAthletes * 100) }}%"></span></div>
                                </div>
                            @empty
                                <p class="hub-empty">No athletes have been registered yet.</p>
                            @endforelse
                        </div>

                        <div class="hub-chart">
                            <h3 class="hub-chart-title">Achievements by Sport</h3>
                            <p class="hub-chart-sub">{{ $achievementsBySport->sum('total') }} achievement{{ $achievementsBySport->sum('total') === 1 ? '' : 's' }} split across {{ $achievementsBySport->count() }} sport{{ $achievementsBySport->count() === 1 ? '' : 's' }}</p>
                            @forelse ($achievementsBySport as $record)
                                <div class="hub-bar">
                                    <div class="hub-bar-label"><span>{{ $record->sport?->name ?? 'Unknown sport' }}</span><strong>{{ $record->total }}</strong></div>
                                    <div class="progress-track" role="progressbar" aria-label="{{ $record->sport?->name ?? 'Sport' }} achievement count" aria-valuemin="0" aria-valuemax="{{ $largestSportAchievements }}" aria-valuenow="{{ $record->total }}"><span style="width: {{ round($record->total / $largestSportAchievements * 100) }}%"></span></div>
                                </div>
                            @empty
                                <p class="hub-empty">No achievements have been recorded yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            {{-- Recent achievements --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">Latest wins</span>
                        <h2 class="hub-panel-title">Recent Achievements</h2>
                        <p class="hub-panel-sub">The {{ $recentAchievements->count() }} most recent recorded achievement{{ $recentAchievements->count() === 1 ? '' : 's' }}</p>
                    </div>
                    <a class="hub-panel-link" href="{{ route('admin.achievements') }}">View all</a>
                </header>
                @if ($recentAchievements->isNotEmpty())
                    <div class="table-wrap hub-table-wrap">
                        <table class="data-table hub-table">
                            <thead><tr><th>Athlete</th><th>Sport</th><th>Achievement</th><th>Competition / Event</th><th>Date</th><th>Place</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($recentAchievements as $record)
                                    <tr>
                                        <td><strong>{{ $record->athlete?->name ?? 'Unknown athlete' }}</strong>@if ($record->athlete?->student_id)<br><small class="meta">{{ $record->athlete->student_id }}</small>@endif</td>
                                        <td>{{ $record->sportLabel() }}</td>
                                        <td>{{ $record->title }}</td>
                                        <td>{{ $record->competitionLabel() ?: 'Not recorded' }}</td>
                                        <td>{{ $record->dateAchievedLabel() }}</td>
                                        <td>{{ $record->place ?: '&mdash;' }}</td>
                                        <td class="hub-table-action"><a class="text-link" href="{{ route('admin.achievements.certificate.print', $record) }}">Certificate</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="hub-panel-body">
                        <p class="hub-empty">No achievements have been recorded yet.</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- ============================ SUPPORTING ============================ --}}
        <aside class="hub-column hub-column--side">

            {{-- Pending applications --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">Review queue</span>
                        <h2 class="hub-panel-title">Pending Applications</h2>
                        <p class="hub-panel-sub">Waiting for a decision</p>
                    </div>
                    <a class="hub-panel-link" href="{{ route('admin.applications') }}">Review all</a>
                </header>
                <div class="hub-panel-body hub-panel-body--flush">
                    @forelse ($pendingApplications as $application)
                        <a class="hub-row hub-row--compact" href="{{ route('applications.show', $application) }}">
                            <span class="hub-row-main">
                                <strong>{{ $application->name }}</strong>
                                <small>{{ $application->sportCategory?->name ?? $application->sport ?? 'Not specified' }} &middot; {{ $application->submitted_at?->format('M j, Y') ?? 'Not recorded' }}</small>
                            </span>
                            <span class="hub-badge hub-badge--{{ $applicationTone($application->status) }}">{{ $application->status }}</span>
                        </a>
                    @empty
                        <p class="hub-empty">There are no pending applications right now.</p>
                    @endforelse
                </div>
            </section>

            {{-- Attendance today --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">Attendance</span>
                        <h2 class="hub-panel-title">Today&rsquo;s Sessions</h2>
                        <p class="hub-panel-sub">{{ $attendanceToday['sessions'] }} session{{ $attendanceToday['sessions'] === 1 ? '' : 's' }} &middot; {{ $attendanceToday['openSessions'] }} still open</p>
                    </div>
                    <a class="hub-panel-link" href="{{ route('admin.attendance') }}">Manage</a>
                </header>
                <div class="hub-panel-body">
                    <ul class="hub-stats">
                        <li class="hub-stat hub-stat--success"><span>Present</span><strong>{{ $attendanceToday['present'] }}</strong></li>
                        <li class="hub-stat hub-stat--warning"><span>Late</span><strong>{{ $attendanceToday['late'] }}</strong></li>
                        <li class="hub-stat hub-stat--danger"><span>Absent</span><strong>{{ $attendanceToday['absent'] }}</strong></li>
                        <li class="hub-stat hub-stat--neutral"><span>Excused</span><strong>{{ $attendanceToday['excused'] }}</strong></li>
                        <li class="hub-stat hub-stat--info"><span>Pending</span><strong>{{ $attendanceToday['pending'] }}</strong></li>
                        <li class="hub-stat hub-stat--primary"><span>Rate</span><strong>{{ $attendanceToday['rate'] }}%</strong></li>
                    </ul>
                </div>
            </section>

            {{-- Medical clearance --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">Health monitoring</span>
                        <h2 class="hub-panel-title">Medical Clearance</h2>
                        <p class="hub-panel-sub">{{ $medicalClearance['cleared'] }} of {{ $medicalClearance['athletes'] }} athletes cleared</p>
                    </div>
                    <a class="hub-panel-link" href="{{ route('admin.medical') }}">Records</a>
                </header>
                <div class="hub-panel-body">
                    @php($medicalTotal = max(1, array_sum($medicalStatusCounts)))
                    <ul class="hub-status-list">
                        @foreach (['Cleared' => 'success', 'Pending' => 'warning', 'Not Cleared' => 'danger', 'Restricted' => 'violet'] as $status => $tone)
                            <li class="hub-status-row">
                                <span class="hub-status-name"><span class="hub-status-dot hub-status-dot--{{ $tone }}" aria-hidden="true"></span>{{ $status }}</span>
                                <span class="hub-status-bar"><span class="hub-status-bar-fill hub-status-bar-fill--{{ $tone }}" style="width: {{ round((($medicalStatusCounts[$status] ?? 0) / $medicalTotal) * 100) }}%"></span></span>
                                <strong>{{ $medicalStatusCounts[$status] ?? 0 }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Leaderboard --}}
            <section class="hub-panel">
                <header class="hub-panel-head">
                    <div class="hub-panel-headings">
                        <span class="hub-panel-kicker">Leaderboard</span>
                        <h2 class="hub-panel-title">Top Performing Sports</h2>
                        <p class="hub-panel-sub">Ranked by total achievements</p>
                    </div>
                </header>
                <div class="hub-panel-body">
                    @if ($achievementsBySport->isNotEmpty())
                        <ol class="hub-rank-list">
                            @foreach ($achievementsBySport->take(5) as $rank => $record)
                                <li class="hub-rank">
                                    <span class="hub-rank-number hub-rank-number--{{ min($rank + 1, 4) }}">{{ $rank + 1 }}</span>
                                    <span class="hub-rank-body">
                                        <strong>{{ $record->sport?->name ?? 'Unknown sport' }}</strong>
                                        <span class="hub-rank-bar"><span style="width: {{ round($record->total / $largestSportAchievements * 100) }}%"></span></span>
                                    </span>
                                    <span class="hub-rank-value">{{ $record->total }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="hub-empty">No achievements have been recorded yet.</p>
                    @endif
                </div>
            </section>
        </aside>
    </div>

    {{-- ====================================================================
         RECENT ACTIVITY + QUICK ACTIONS
         ==================================================================== --}}
    <div class="hub-band">
        <section class="hub-panel">
            <header class="hub-panel-head">
                <div class="hub-panel-headings">
                    <span class="hub-panel-kicker">Latest changes</span>
                    <h2 class="hub-panel-title">Recent Activity</h2>
                    <p class="hub-panel-sub">The most recent updates across the hub</p>
                </div>
            </header>
            <div class="hub-panel-body">
                @forelse ($recentActivity as $activity)
                    <div class="hub-activity">
                        <span class="hub-activity-dot" aria-hidden="true"></span>
                        <div>
                            <p>{{ $activity['label'] }}</p>
                            <small>{{ $activity['date']?->diffForHumans() ?? 'Recently' }}</small>
                        </div>
                    </div>
                @empty
                    <p class="hub-empty">No activity has been recorded yet.</p>
                @endforelse
            </div>
        </section>

        <section class="hub-panel">
            <header class="hub-panel-head">
                <div class="hub-panel-headings">
                    <span class="hub-panel-kicker">Shortcuts</span>
                    <h2 class="hub-panel-title">Quick Actions</h2>
                    <p class="hub-panel-sub">Jump straight to the module you need</p>
                </div>
            </header>
            <div class="hub-panel-body">
                @php($quickActions = [
                    ['label' => 'Create Event', 'hint' => 'Schedule a session or competition', 'route' => 'events.create', 'icon' => 'calendar', 'tone' => 'primary'],
                    ['label' => 'Review Applications', 'hint' => 'Decide on submitted applications', 'route' => 'admin.applications', 'icon' => 'clipboard', 'tone' => 'warning'],
                    ['label' => 'Manage Athletes', 'hint' => 'View and edit athlete accounts', 'route' => 'athletes.index', 'icon' => 'users', 'tone' => 'success'],
                    ['label' => 'Take Attendance', 'hint' => 'Open sessions and record attendance', 'route' => 'admin.attendance', 'icon' => 'activity', 'tone' => 'info'],
                    ['label' => 'Record Achievement', 'hint' => 'Award a medal or certificate', 'route' => 'admin.achievements', 'icon' => 'trophy', 'tone' => 'warning'],
                    ['label' => 'Medical Records', 'hint' => 'Manage clearances and checkups', 'route' => 'admin.medical', 'icon' => 'medical', 'tone' => 'danger'],
                    ['label' => 'Manage Sports', 'hint' => 'Add or edit sports programs', 'route' => 'sports.create', 'icon' => 'trophy', 'tone' => 'accent'],
                    ['label' => 'Reports', 'hint' => 'Generate attendance and roster reports', 'route' => 'reports.index', 'icon' => 'chart', 'tone' => 'primary'],
                ])
                <div class="hub-quick-grid">
                    @foreach ($quickActions as $action)
                        <a class="hub-quick" href="{{ route($action['route']) }}" title="{{ $action['hint'] }}">
                            <span class="hub-quick-icon hub-tone-{{ $action['tone'] }}" aria-hidden="true">
                                <svg aria-hidden="true"><use href="#icon-{{ $action['icon'] }}"></use></svg>
                            </span>
                            <span class="hub-quick-text">
                                <strong>{{ $action['label'] }}</strong>
                                <small>{{ $action['hint'] }}</small>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>
