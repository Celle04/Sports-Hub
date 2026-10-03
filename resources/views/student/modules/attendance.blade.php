@php
	$stats = $attendanceStats ?? ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'pending' => 0, 'rate' => 0];
	$records = $attendanceRecords ?? collect();
	$openSessions = $openAttendanceSessions ?? collect();
	$upcomingSessions = $upcomingAttendanceSessions ?? collect();
	$recordFor = fn ($session) => $records->first(fn ($record) => (int) $record->attendance_session_id === (int) $session->id);
	$timeRange = fn ($session) => ($session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') : 'Time not set')
		.($session->end_time ? ' - '.\Illuminate\Support\Carbon::parse($session->end_time)->format('g:i A') : '');
@endphp

<section class="student-attendance-summary" aria-label="Attendance summary">
	<div class="card student-attendance-stat student-attendance-rate"><span class="student-attendance-stat-icon"><svg aria-hidden="true"><use href="#icon-chart"></use></svg></span><div><strong>{{ $stats['rate'] }}%</strong><small>Attendance rate</small></div></div>
	<div class="card student-attendance-stat student-attendance-present"><span class="student-attendance-stat-icon"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span><div><strong>{{ $stats['present'] + $stats['late'] }}</strong><small>Present and late</small></div></div>
	<div class="card student-attendance-stat student-attendance-absent"><span class="student-attendance-stat-icon"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span><div><strong>{{ $stats['absent'] }}</strong><small>Absent</small></div></div>
</section>

@if ($openSessions->isNotEmpty())
	<section class="student-attendance-open">
		<header class="student-attendance-section-heading"><div><span>CHECK-IN AVAILABLE</span><h2>Active sessions</h2></div><span class="student-attendance-open-count">{{ $openSessions->count() }} {{ $openSessions->count() === 1 ? 'session' : 'sessions' }}</span></header>
		@foreach ($openSessions as $session)
			@php($record = $recordFor($session))
			<article class="card student-attendance-session">
				<div class="student-attendance-session-info"><span class="student-attendance-session-label">NOW CHECKING IN</span><h3>{{ $session->displayName() }}</h3><p>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $timeRange($session) }} &middot; {{ $session->venue ?: 'Venue not set' }}</p><p class="meta">{{ $session->effectiveSportName() }}{{ $session->description ? ' · '.$session->description : '' }}</p></div>
				@if ($record && $record->wasCheckedIn())
					<span class="badge status-present student-attendance-checked-in">Checked in <small>{{ $record->check_in_time?->format('g:i A') }}</small></span>
				@elseif ($record && ! $record->isPending())
					<span class="badge">{{ $record->status }}</span>
				@else
					<form method="POST" action="{{ route('student.attendance.check-in', $session) }}">@csrf<button class="button student-attendance-check-in" type="submit"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg>Check In</button></form>
				@endif
			</article>
		@endforeach
	</section>
@endif

@if ($upcomingSessions->isNotEmpty())
	<section class="student-attendance-open">
		<header class="student-attendance-section-heading"><div><span>COMING UP</span><h2>Upcoming attendance</h2></div></header>
		@foreach ($upcomingSessions as $session)
			<article class="card student-attendance-session"><div class="student-attendance-session-info"><h3>{{ $session->displayName() }}</h3><p>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $timeRange($session) }} &middot; {{ $session->venue ?: 'Venue not set' }}</p><p class="meta">{{ $session->effectiveSportName() }} &middot; Status: {{ $session->status }}</p></div><span class="badge status-pending">{{ $recordFor($session)?->status ?? 'Expected' }}</span></article>
		@endforeach
	</section>
@endif

<section class="student-attendance-history">
	<header class="student-attendance-section-heading"><div><span>YOUR RECORD</span><h2>Attendance history</h2><p class="meta">Sessions for {{ $athlete->sport?->name ?? 'your assigned sport' }}</p></div></header>
	<div class="table-wrap student-attendance-table-wrap">
		<table class="data-table student-attendance-table">
			<thead><tr><th>Date</th><th>Session</th><th>Sport</th><th>Venue</th><th>Status</th><th>Check-in time</th></tr></thead>
			<tbody>
				@forelse ($records as $record)
					<tr><td>{{ $record->attended_on?->format('M j, Y') }}</td><td>{{ $record->displayName() }}</td><td>{{ $record->sportName() }}</td><td>{{ $record->venue() ?: '--' }}</td><td><span class="badge student-attendance-badge status-{{ strtolower(str_replace(' ', '-', $record->status)) }}">{{ $record->status }}</span></td><td>{{ $record->check_in_time?->format('g:i A') ?? '--' }}</td></tr>
				@empty
					<tr><td colspan="6" class="student-attendance-no-records">No attendance records yet.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</section>
