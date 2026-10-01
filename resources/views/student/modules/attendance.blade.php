@php($present = ($attendanceRecords ?? collect())->where('status', 'Present')->count())

@if (!empty($openAttendanceSessions) && $openAttendanceSessions->isNotEmpty())
	<section class="card" style="margin-bottom:16px">
		<h2>Attendance Available</h2>
		@foreach ($openAttendanceSessions as $session)
			<article class="event-preview" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
				<div><strong>{{ $session->title }}</strong><p>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $session->start_time ? \Carbon\Carbon::parse($session->start_time)->format('g:i A') : 'Time not set' }}{{ $session->end_time ? ' - '.\Carbon\Carbon::parse($session->end_time)->format('g:i A') : '' }} &middot; {{ $session->venue ?? 'Venue not set' }}</p><p class="meta">{{ $session->sport?->name ?? $session->event?->sport?->name }}{{ $session->description ? ' · '.$session->description : '' }}</p></div>
				@if (!$attendanceRecords->contains(fn ($record) => (int) $record->attendance_session_id === (int) $session->id && in_array($record->status, ['Present', 'Late'], true)))
					<form method="POST" action="{{ route('student.attendance.check-in', $session) }}">@csrf<button class="button" type="submit">Check In</button></form>
				@else
					<span class="badge status-present">Checked In</span>
				@endif
			</article>
		@endforeach
	</section>
@endif

<div class="grid grid-3">
	<div class="card stat">
		<strong>{{ ($attendanceRecords ?? collect())->count() ? round($present / $attendanceRecords->count() * 100) : 0 }}%</strong>
		<small>Attendance rate</small>
	</div>
	<div class="card stat">
		<strong>{{ $present }}</strong>
		<small>Present</small>
	</div>
	<div class="card stat">
		<strong>{{ ($attendanceRecords ?? collect())->where('status', 'Absent')->count() }}</strong>
		<small>Absent</small>
	</div>
</div>

<div class="table-wrap" style="margin-top:16px">
	<table class="data-table">
		<thead><tr><th>Date</th><th>Activity</th><th>Status</th><th>Check-in time</th></tr></thead>
		<tbody>
			@forelse ($attendanceRecords ?? [] as $record)
				<tr>
					<td>{{ $record->attended_on->format('M j, Y') }}</td>
					<td>{{ $record->session?->title ?? $record->event?->title ?? 'Attendance' }}</td>
					<td>{{ $record->status }}</td>
					<td>{{ $record->check_in_time?->format('g:i A') ?? 'Manual entry' }}</td>
				</tr>
			@empty
				<tr><td colspan="4">No attendance records yet.</td></tr>
			@endforelse
		</tbody>
	</table>
</div>
