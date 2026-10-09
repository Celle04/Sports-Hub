@php
	$summary = $summary ?? [];
	$sessions = $attendanceSessions ?? collect();
	$filters = $filters ?? [];
	$selected = $selectedAttendanceSession ?? null;
	$records = $selected ? ($attendanceSessionRecords ?? collect()) : collect();
	$statuses = $sessionStatuses ?? ['Open', 'Closed', 'Cancelled'];
	$recordStatuses = $recordStatuses ?? ['Present', 'Late', 'Absent', 'Excused'];
	$tally = fn ($session) => [
		'total' => (int) $session->total_count,
		'present' => (int) $session->present_count,
		'late' => (int) $session->late_count,
		'pending' => (int) $session->pending_count,
		'absent' => (int) $session->absent_count,
		'excused' => (int) $session->excused_count,
	];
@endphp

<div class="grid grid-4">
	<div class="card stat"><small>Today's Sessions</small><strong>{{ $summary['todaySessions'] ?? 0 }}</strong><small class="meta">{{ \Illuminate\Support\Carbon::parse($summary['date'] ?? today())->format('M j, Y') }}</small></div>
	<div class="card stat"><small>Open Sessions</small><strong>{{ $summary['openSessions'] ?? 0 }}</strong><small class="meta">Athletes can check in</small></div>
	<div class="card stat"><small>Present Today</small><strong>{{ $summary['presentToday'] ?? 0 }}</strong><small class="meta">{{ $summary['lateToday'] ?? 0 }} late</small></div>
	<div class="card stat"><small>Pending Today</small><strong>{{ $summary['pendingToday'] ?? 0 }}</strong><small class="meta">Awaiting check-in</small></div>
</div>

<div class="grid grid-4" style="margin-top:16px">
	<div class="card stat"><small>Total Athletes</small><strong>{{ $summary['totalAthletes'] ?? 0 }}</strong><small class="meta">Registered student accounts</small></div>
	<div class="card stat"><small>Absent</small><strong>{{ $summary['absentToday'] ?? 0 }}</strong><small class="meta">Manually marked</small></div>
	<div class="card stat"><small>Attendance Rate</small><strong>{{ $summary['attendanceRate'] ?? 0 }}%</strong><small class="meta">Pending excluded</small></div>
	<div class="card stat"><small>Sessions Listed</small><strong>{{ $sessions->count() }}</strong><small class="meta">Matching current filters</small></div>
</div>

<section class="card" style="margin-top:18px;padding:18px;">
	<div class="section-heading">
		<div>
			<h2>Create Attendance Session</h2>
			<p class="meta">Choose the sport. Athletes are assigned automatically from their assigned sport. There is no manual student selection.</p>
		</div>
	</div>

	<form method="POST" action="{{ route('admin.attendance.sessions.store') }}">
		@csrf
		<div class="form-grid">
			<div class="form-group"><label>Attendance Title</label><input class="form-control" name="title" placeholder="Session title" required></div>
			<div class="form-group">
				<label for="attendance-sport">Sport</label>
				<select class="form-control" id="attendance-sport" name="sport_id">
					<option value="">All Sports (every active athlete)</option>
					@foreach ($sports ?? [] as $sport)
						<option value="{{ $sport->id }}">{{ $sport->name }} ({{ $sport->athletes_count ?? 0 }} athletes)</option>
					@endforeach
				</select>
				<small class="meta"><label for="sport-required" style="display:inline;font-weight:400"><input type="checkbox" id="sport-required" name="sport_required" value="1" style="width:auto;margin-right:6px">Require a specific sport</label></small>
				@error('sport_id')<small class="form-error">{{ $message }}</small>@enderror
			</div>
			<div class="form-group"><label>Date</label><input class="form-control" type="date" name="session_date" value="{{ today()->toDateString() }}" required></div>
			<div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="Open">Open</option><option value="Closed">Closed</option><option value="Cancelled">Cancelled</option></select></div>
			<div class="form-group"><label>Start Time</label><input class="form-control" type="time" name="start_time"></div>
			<div class="form-group"><label>End Time</label><input class="form-control" type="time" name="end_time"></div>
			<div class="form-group"><label>Late Grace (minutes)</label><input class="form-control" type="number" name="late_grace_minutes" min="0" max="120" value="{{ config('attendance.late_grace_minutes') }}"></div>
			<div class="form-group"><label>Venue</label><input class="form-control" name="venue" placeholder="SNNHS Gymnasium"></div>
		</div>
		<div class="form-group" style="margin-top:12px"><label>Description</label><textarea class="form-control" name="description" rows="2" placeholder="Regular basketball training session"></textarea></div>
		<button class="button" type="submit">Create Attendance Session</button>
	</form>
	@foreach (['title', 'session_date', 'start_time', 'end_time', 'venue', 'status'] as $field) @error($field)<small class="form-error">{{ $message }}</small>@enderror @endforeach
</section>

<section class="card" style="margin-top:18px;padding:18px;">
	<h2>Filters</h2>
	<form method="GET" action="{{ route('admin.attendance') }}" class="filters">
		<select class="form-control" name="sport_id">
			<option value="">All Sports</option>
			@foreach ($sports ?? [] as $sport)<option value="{{ $sport->id }}" @selected(($filters['sport_id'] ?? null) == $sport->id)>{{ $sport->name }}</option>@endforeach
		</select>
		<input class="form-control" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
		<select class="form-control" name="status">
			<option value="">All Status</option>
			@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach
		</select>
		<select class="form-control" name="record_status">
			<option value="">All Record Status</option>
			<option value="Pending" @selected(($filters['record_status'] ?? null) === 'Pending')>Pending</option>
			@foreach ($recordStatuses as $status)<option value="{{ $status }}" @selected(($filters['record_status'] ?? null) === $status)>{{ $status }}</option>@endforeach
		</select>
		<input class="form-control" type="search" name="search" placeholder="Search session or athlete..." value="{{ $filters['search'] ?? '' }}">
		<button class="button" type="submit">Apply</button>
		<a class="button button-muted" href="{{ route('admin.attendance') }}">Reset</a>
	</form>
</section>

<section class="card" style="margin-top:18px;padding:18px;">
	<div class="section-heading">
		<div><h2>Attendance Sessions</h2><p class="meta">Sessions reference a sport; rosters follow the athlete sport assignment.</p></div>
	</div>
	<div class="table-wrap" style="margin-top:12px">
		<table class="data-table">
			<thead><tr><th>Date</th><th>Session</th><th>Sport</th><th>Venue</th><th>Total Athletes</th><th>Present</th><th>Pending</th><th>Status</th><th>Actions</th></tr></thead>
			<tbody>
				@forelse ($sessions as $session)
					@php($counts = $tally($session))
					<tr>
						<td>{{ $session->session_date?->format('M j, Y') }}<br><small class="meta">{{ $session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') : 'Time not set' }}{{ $session->end_time ? ' - '.\Illuminate\Support\Carbon::parse($session->end_time)->format('g:i A') : '' }}</small></td>
						<td><strong>{{ $session->displayName() }}</strong></td>
						<td>{{ $session->effectiveSportName() }}</td>
						<td>{{ $session->venue ?: '--' }}</td>
						<td>{{ $counts['total'] }}</td>
						<td>{{ $counts['present'] }}@if ($counts['late'])<small class="meta">+{{ $counts['late'] }} late</small>@endif</td>
						<td>{{ $counts['pending'] }}</td>
						<td><span class="badge {{ $session->status === 'Open' ? 'status-present' : ($session->status === 'Cancelled' ? 'status-absent' : 'status-pending') }}">{{ $session->status }}</span></td>
						<td>
							<a class="text-link" href="{{ route('admin.attendance', array_merge(request()->query(), ['session_id' => $session->id])) }}">View Attendance</a>
							@if ($session->status !== 'Open' && $session->status !== 'Cancelled')
								<form method="POST" action="{{ route('admin.attendance.sessions.open', $session) }}" style="display:inline">@csrf @method('PATCH')<button class="text-link" type="submit">Open</button></form>
							@elseif ($session->status === 'Open')
								<form method="POST" action="{{ route('admin.attendance.sessions.close', $session) }}" style="display:inline">@csrf @method('PATCH')<button class="text-link" type="submit">Close Attendance</button></form>
							@endif
							<form method="POST" action="{{ route('admin.attendance.sessions.sync', $session) }}" style="display:inline">@csrf @method('PATCH')<button class="text-link" type="submit">Sync Roster</button></form>
						</td>
					</tr>
				@empty
					<tr><td colspan="9" class="empty-cell">No attendance sessions match your filters.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</section>

@if ($selected)
	<section class="card" style="margin-top:18px;padding:18px;">
		<div class="section-heading">
			<div>
				<h2>{{ $selected->displayName() }}</h2>
				<p class="meta">
					{{ $selected->session_date?->format('F j, Y') }} &middot;
					{{ $selected->start_time ? \Illuminate\Support\Carbon::parse($selected->start_time)->format('g:i A') : 'Time not set' }}
					@if ($selected->end_time) - {{ \Illuminate\Support\Carbon::parse($selected->end_time)->format('g:i A') }} @endif
					&middot; {{ $selected->venue ?: 'Venue not set' }} &middot; {{ $selected->effectiveSportName() }}
				</p>
				@if ($selected->description)<p class="meta">{{ $selected->description }}</p>@endif
			</div>
			<span class="badge {{ $selected->status === 'Open' ? 'status-present' : ($selected->status === 'Cancelled' ? 'status-absent' : 'status-pending') }}">{{ $selected->status }}</span>
		</div>

		<div class="grid grid-4" style="margin-top:12px">
			@php($selectedTally = ['total' => $records->count(), 'present' => $records->where('status', 'Present')->count(), 'late' => $records->where('status', 'Late')->count(), 'pending' => $records->where('status', 'Pending')->count(), 'absent' => $records->where('status', 'Absent')->count(), 'excused' => $records->where('status', 'Excused')->count()])
			<div class="card stat"><small>Total</small><strong>{{ $selectedTally['total'] }}</strong></div>
			<div class="card stat"><small>Present</small><strong>{{ $selectedTally['present'] }}</strong></div>
			<div class="card stat"><small>Late</small><strong>{{ $selectedTally['late'] }}</strong></div>
			<div class="card stat"><small>Pending</small><strong>{{ $selectedTally['pending'] }}</strong></div>
			<div class="card stat"><small>Absent</small><strong>{{ $selectedTally['absent'] }}</strong></div>
			<div class="card stat"><small>Excused</small><strong>{{ $selectedTally['excused'] }}</strong></div>
		</div>

		<div class="event-actions" style="margin-top:14px">
			@if ($selected->status === 'Open')
				<form method="POST" action="{{ route('admin.attendance.sessions.close', $selected) }}">@csrf @method('PATCH')<button class="button" type="submit">Close Attendance</button></form>
			@elseif ($selected->status === 'Closed')
				<form method="POST" action="{{ route('admin.attendance.sessions.open', $selected) }}">@csrf @method('PATCH')<button class="button" type="submit">Reopen Attendance</button></form>
			@endif
			@if ($selected->status !== 'Cancelled')
				<form method="POST" action="{{ route('admin.attendance.sessions.cancel', $selected) }}">@csrf @method('PATCH')<button class="button button-muted" type="submit">Cancel Session</button></form>
			@endif
			<a class="button button-secondary" href="{{ route('admin.attendance.sessions.export', $selected) }}">Export Report</a>
			<button class="button button-danger" type="button" data-confirm-dialog data-confirm-title="Delete Attendance Session?" data-confirm-message="Delete this attendance session and all of its records?" data-confirm-label="Delete Session" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.attendance.sessions.destroy', $selected) }}">Delete Session</button>
		</div>

		<div class="table-wrap" style="margin-top:16px">
			<table class="data-table">
				<thead><tr><th>Athlete</th><th>Student ID</th><th>Sport</th><th>Status</th><th>Check-in</th><th>Correct</th></tr></thead>
				<tbody>
					@forelse ($records as $record)
						<tr>
							<td>{{ $record->athlete?->name }}</td>
							<td>{{ $record->athlete?->student_id ?: 'No ID' }}</td>
							<td>{{ $record->athlete?->sport?->name ?? 'Unassigned' }}</td>
							<td><span class="badge {{ $record->status === 'Present' ? 'status-present' : ($record->status === 'Absent' ? 'status-absent' : ($record->status === 'Pending' ? 'status-pending' : 'status-cleared')) }}">{{ $record->status }}</span></td>
							<td>{{ $record->check_in_time?->format('g:i A') ?? '--' }}</td>
							<td>
								<form method="POST" action="{{ route('admin.attendance.records.update', $record) }}" style="display:flex;gap:6px;align-items:center">
									@csrf @method('PUT')
									<select class="form-control" name="status" style="padding:5px">
										<option value="Pending" @selected($record->status === 'Pending')>Pending</option>
										@foreach ($recordStatuses as $status)<option value="{{ $status }}" @selected($record->status === $status)>{{ $status }}</option>@endforeach
									</select>
									<button class="text-link" type="submit">Save</button>
								</form>
							</td>
						</tr>
					@empty
						<tr><td colspan="6" class="empty-cell">No athletes are assigned to this session's sport yet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</section>
@endif

<section class="card" style="margin-top:18px;padding:18px;">
	<h2>Attendance History</h2>
	<p class="meta">All session and event attendance records matching your filters.</p>
	<div class="table-wrap" style="margin-top:12px">
		<table class="data-table">
			<thead><tr><th>Date</th><th>Session</th><th>Athlete</th><th>Sport</th><th>Venue</th><th>Status</th><th>Check-in</th></tr></thead>
			<tbody>
				@forelse ($attendance ?? [] as $row)
					<tr>
						<td>{{ $row->attended_on?->format('M j, Y') }}</td>
						<td>{{ $row->displayName() }}</td>
						<td>{{ $row->athlete?->name }}</td>
						<td>{{ $row->sportName() }}</td>
						<td>{{ $row->venue() ?: '--' }}</td>
						<td><span class="badge">{{ $row->status }}</span></td>
						<td>{{ $row->check_in_time?->format('g:i A') ?? '--' }}</td>
					</tr>
				@empty
					<tr><td colspan="7" class="empty-cell">No attendance records match your filters.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</section>
