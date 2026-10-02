@php
	$stats = $attendanceStats ?? ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'pending' => 0, 'rate' => 0];
	$records = $attendanceRecords ?? collect();
	$openSessions = $openAttendanceSessions ?? collect();
	$upcomingSessions = $upcomingAttendanceSessions ?? collect();
	$recordFor = fn ($session) => $records->first(fn ($record) => (int) $record->attendance_session_id === (int) $session->id);
	$timeRange = fn ($session) => ($session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') : 'Time not set')
		.($session->end_time ? ' - '.\Illuminate\Support\Carbon::parse($session->end_time)->format('g:i A') : '');
@endphp

<div class="grid grid-4">
	<div class="card stat"><strong>{{ $stats['rate'] }}%</strong><small>Attendance rate</small></div>
	<div class="card stat"><strong>{{ $stats['present'] }}</strong><small>Present</small></div>
	<div class="card stat"><strong>{{ $stats['late'] }}</strong><small>Late</small></div>
	<div class="card stat"><strong>{{ $stats['absent'] }}</strong><small>Absent</small></div>
	<div class="card stat"><strong>{{ $stats['excused'] }}</strong><small>Excused</small></div>
	<div class="card stat"><strong>{{ $stats['pending'] }}</strong><small>Pending</small></div>
	<div class="card stat"><strong>{{ $stats['total'] }}</strong><small>Sessions counted</small></div>
</div>

@if ($openSessions->isNotEmpty())
	<section class="card" style="margin-top:16px;padding:18px;">
		<div class="section-heading"><div><h2>Today's Attendance</h2><p class="meta">Sessions open for your assigned sport. Check in to record your attendance.</p></div></div>
		@foreach ($openSessions as $session)
			@php($record = $recordFor($session))
			<article class="event-preview" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
				<div>
					<strong>{{ $session->displayName() }}</strong>
					<p>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $timeRange($session) }} &middot; {{ $session->venue ?: 'Venue not set' }}</p>
					<p class="meta">{{ $session->effectiveSportName() }}{{ $session->description ? ' · '.$session->description : '' }}</p>
				</div>
				@if ($record && $record->wasCheckedIn())
					<span><span class="badge status-present">Checked In</span><br><small class="meta">{{ $record->check_in_time?->format('g:i A') }}</small></span>
				@elseif ($record && ! $record->isPending())
					<span class="badge">{{ $record->status }}</span>
				@else
					<form method="POST" action="{{ route('student.attendance.check-in', $session) }}">@csrf<button class="button" type="submit">Check In</button></form>
				@endif
			</article>
		@endforeach
	</section>
@endif

@if ($upcomingSessions->isNotEmpty())
	<section class="card" style="margin-top:16px;padding:18px;">
		<div class="section-heading"><div><h2>Upcoming Attendance</h2><p class="meta">Check-in opens on the session date.</p></div></div>
		@foreach ($upcomingSessions as $session)
			<article class="event-preview" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
				<div>
					<strong>{{ $session->displayName() }}</strong>
					<p>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $timeRange($session) }} &middot; {{ $session->venue ?: 'Venue not set' }}</p>
					<p class="meta">{{ $session->effectiveSportName() }} &middot; Status: {{ $session->status }}</p>
				</div>
				<span class="badge status-pending">{{ $recordFor($session)?->status ?? 'Expected' }}</span>
			</article>
		@endforeach
	</section>
@endif

<section class="card" style="margin-top:16px;padding:18px;">
	<div class="section-heading"><div><h2>My Attendance History</h2><p class="meta">Only sessions for {{ $athlete->sport?->name ?? 'your assigned sport' }} are listed.</p></div></div>
	<div class="table-wrap" style="margin-top:12px">
		<table class="data-table">
			<thead><tr><th>Date</th><th>Session</th><th>Sport</th><th>Venue</th><th>Status</th><th>Check-in Time</th></tr></thead>
			<tbody>
				@forelse ($records as $record)
					<tr>
						<td>{{ $record->attended_on?->format('M j, Y') }}</td>
						<td>{{ $record->displayName() }}</td>
						<td>{{ $record->sportName() }}</td>
						<td>{{ $record->venue() ?: '--' }}</td>
						<td><span class="badge {{ $record->status === 'Present' ? 'status-present' : ($record->status === 'Absent' ? 'status-absent' : ($record->status === 'Pending' ? 'status-pending' : 'status-cleared')) }}">{{ $record->status }}</span></td>
						<td>{{ $record->check_in_time?->format('g:i A') ?? '--' }}</td>
					</tr>
				@empty
					<tr><td colspan="6" class="empty-cell">No attendance records yet.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</section>
