@php($attendanceSummary = $summary ?? ['totalAthletes' => 0, 'presentToday' => 0, 'absentToday' => 0, 'attendanceRate' => 0])

<div class="grid grid-4">
	<div class="card stat"><small>Today's Sessions</small><strong>{{ $attendanceSummary['todaySessions'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Open Sessions</small><strong>{{ $attendanceSummary['openSessions'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Present Today</small><strong>{{ $attendanceSummary['presentToday'] }}</strong></div>
	<div class="card stat"><small>Pending Today</small><strong>{{ $attendanceSummary['pendingToday'] ?? 0 }}</strong></div>
</div>

<div class="grid grid-4" style="margin-top:16px">
	<div class="card stat"><small>Total Athletes</small><strong>{{ $attendanceSummary['totalAthletes'] }}</strong></div>
	<div class="card stat"><small>Absent Today</small><strong>{{ $attendanceSummary['absentToday'] }}</strong></div>
	<div class="card stat"><small>Excused Today</small><strong>{{ $attendanceSummary['excusedToday'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Attendance Rate</small><strong>{{ $attendanceSummary['attendanceRate'] }}%</strong></div>
</div>

<section class="card" style="margin-top:18px;padding:18px;">
	<h2>Attendance Sessions</h2>
	<p class="meta">Create a session, then open it when students may check in.</p>
	<form method="POST" action="{{ route('admin.attendance.sessions.store') }}">
		@csrf
		<div class="grid grid-4">
			<div class="form-group"><label>Session name</label><input class="form-control" name="title" placeholder="Daily Basketball Training" required></div>
			<div class="form-group"><label>Event</label><select class="form-control" name="event_id"><option value="">Daily training / no event</option>@foreach ($attendanceEvents ?? [] as $event)<option value="{{ $event->id }}">{{ $event->title }}</option>@endforeach</select></div>
			<div class="form-group"><label>Sport</label><select class="form-control" name="sport_id"><option value="">Use event sport</option>@foreach ($sports ?? [] as $sport)<option value="{{ $sport->id }}">{{ $sport->name }}</option>@endforeach</select></div>
			<div class="form-group"><label>Date</label><input class="form-control" type="date" name="session_date" value="{{ now()->toDateString() }}" required></div>
		</div>
		<div class="grid grid-3" style="margin-top:12px"><div class="form-group"><label>Start time</label><input class="form-control" type="time" name="start_time"></div><div class="form-group"><label>End time</label><input class="form-control" type="time" name="end_time"></div><div class="form-group"><label>Venue</label><input class="form-control" name="venue" placeholder="SNNHS Gymnasium"></div></div>
		<div class="form-group" style="margin-top:12px"><label>Description</label><textarea class="form-control" name="description" rows="2" placeholder="Regular sports training session"></textarea></div>
		<button class="button" type="submit">Create Session</button>
	</form>

	<div class="table-wrap" style="margin-top:18px"><table class="data-table"><thead><tr><th>Session</th><th>Sport</th><th>Date</th><th>Roster</th><th>Status</th><th>Actions</th></tr></thead><tbody>
		@forelse ($attendanceSessions ?? [] as $session)
			<tr><td><strong>{{ $session->title }}</strong><br><small>{{ $session->event?->title ?? 'Daily training' }} &middot; {{ $session->venue ?? 'Venue not set' }}</small></td><td>{{ $session->sport?->name ?? $session->event?->sport?->name ?? 'All sports' }}</td><td>{{ $session->session_date?->format('M j, Y') }}</td><td>{{ $session->attendance_records_count ?? $session->present_count + $session->pending_count + $session->late_count + $session->absent_count }} total<br><small>{{ $session->present_count }} present / {{ $session->pending_count }} pending</small></td><td><span class="badge">{{ $session->status }}</span></td><td><a class="text-link" href="{{ route('admin.attendance', ['session_id' => $session->id]) }}">View</a>@if($session->status !== 'Open')<form method="POST" action="{{ route('admin.attendance.sessions.open', $session) }}" style="display:inline">@csrf @method('PATCH')<button class="text-link" type="submit">Open</button></form>@else<form method="POST" action="{{ route('admin.attendance.sessions.close', $session) }}" style="display:inline">@csrf @method('PATCH')<button class="text-link" type="submit">Close</button></form>@endif</td></tr>
		@empty
			<tr><td colspan="6" class="empty-cell">No attendance sessions created yet.</td></tr>
		@endforelse
	</tbody></table></div>

	@if ($selectedAttendanceSession)
		<div class="event-preview"><strong>{{ $selectedAttendanceSession->title }} check-ins</strong><p>{{ $attendanceSessionRecords->count() }} student(s) checked in.</p></div>
		<div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Student ID</th><th>Status</th><th>Check-in Time</th></tr></thead><tbody>@forelse($attendanceSessionRecords as $record)<tr><td>{{ $record->athlete?->name }}</td><td>{{ $record->athlete?->student_id ?? 'No ID' }}</td><td>{{ $record->status }}</td><td>{{ $record->check_in_time?->format('g:i A') ?? 'Manual entry' }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">No students have checked in yet.</td></tr>@endforelse</tbody></table></div>
	@endif
</section>

<section class="card" style="margin-top:18px;padding:18px;">
	<h2>Attendance History</h2>
	<div class="table-wrap"><table class="data-table"><thead><tr><th>Athlete</th><th>Event</th><th>Status</th><th>Date</th></tr></thead><tbody>
		@forelse ($attendance ?? [] as $row)
			<tr><td>{{ $row->athlete?->name }}</td><td>{{ $row->event?->title }}</td><td><span class="badge">{{ $row->status }}</span></td><td>{{ $row->attended_on?->format('M j, Y') }}</td></tr>
		@empty
			<tr><td colspan="4" class="empty-cell">No attendance records match your filters.</td></tr>
		@endforelse
	</tbody></table></div>
</section>
