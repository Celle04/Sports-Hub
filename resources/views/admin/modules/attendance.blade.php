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

<!-- Page header ------------------------------------------------------------- -->
<header class="attendance-page-head">
	<div class="attendance-page-head-copy">
		<span class="attendance-page-kicker">Admin &middot; Attendance</span>
		<h1 class="attendance-page-title">Attendance Management</h1>
		<p class="attendance-page-subtitle">Track and manage athlete attendance across sports sessions.</p>
	</div>
	<div class="attendance-page-head-aside">
		<p class="attendance-page-note">
			<svg aria-hidden="true"><use href="#icon-calendar"></use></svg>
			<span>Attendance sessions are created from the Event module, so every session belongs to an event.</span>
		</p>
		<a class="button button-secondary" href="{{ route('events.index') }}">
			<svg class="button-icon" aria-hidden="true"><use href="#icon-calendar"></use></svg>Open Events
		</a>
	</div>
</header>

@if (session('success'))
	<div class="notice">{{ session('success') }}</div>
@endif

@if ($errors->any())
	<div class="notice notice-error">Please correct the highlighted form fields.</div>
@endif

<!-- Summary metrics --------------------------------------------------------- -->
<section class="attendance-summary" aria-label="Attendance summary">
	<div class="attendance-summary-kpis">
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Today&rsquo;s Sessions</span>
				<strong class="attendance-kpi-value">{{ $summary['todaySessions'] ?? 0 }}</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Open Sessions</span>
				<strong class="attendance-kpi-value">{{ $summary['openSessions'] ?? 0 }}</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-user"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Present Today</span>
				<strong class="attendance-kpi-value">{{ $summary['presentToday'] ?? 0 }}</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-bell"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Pending Today</span>
				<strong class="attendance-kpi-value">{{ $summary['pendingToday'] ?? 0 }}</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-users"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Total Athletes</span>
				<strong class="attendance-kpi-value">{{ $summary['totalAthletes'] ?? 0 }}</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-x"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Absent</span>
				<strong class="attendance-kpi-value">{{ $summary['absentToday'] ?? 0 }}</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-chart"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Attendance Rate</span>
				<strong class="attendance-kpi-value">{{ $summary['attendanceRate'] ?? 0 }}%</strong>
			</div>
		</div>
		<div class="attendance-kpi-card">
			<div class="attendance-kpi-icon"><svg aria-hidden="true"><use href="#icon-file"></use></svg></div>
			<div class="attendance-kpi-body">
				<span class="attendance-kpi-label">Sessions Listed</span>
				<strong class="attendance-kpi-value">{{ $sessions->count() }}</strong>
			</div>
		</div>
	</div>
</section>

<!-- Filters ----------------------------------------------------------------- -->
<section class="attendance-filters-card" aria-label="Filters">
	<header class="attendance-filters-head">
		<svg class="attendance-filters-icon" aria-hidden="true"><use href="#icon-filter"></use></svg>
		<div>
			<span class="attendance-filters-kicker">Filters</span>
			<h2 class="attendance-filters-title">Refine sessions</h2>
		</div>
	</header>
	<form method="GET" action="{{ route('admin.attendance') }}" class="attendance-filters-form">
		<div class="attendance-filters-grid">
			<div class="attendance-filter-field">
				<label>Sport</label>
				<select class="form-control" name="sport_id">
					<option value="">All Sports</option>
					@foreach ($sports ?? [] as $sport)<option value="{{ $sport->id }}" @selected(($filters['sport_id'] ?? null) == $sport->id)>{{ $sport->name }}</option>@endforeach
				</select>
			</div>
			<div class="attendance-filter-field">
				<label>Date</label>
				<input class="form-control" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
			</div>
			<div class="attendance-filter-field">
				<label>Session Status</label>
				<select class="form-control" name="status">
					<option value="">All Status</option>
					@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach
				</select>
			</div>
			<div class="attendance-filter-field">
				<label>Record Status</label>
				<select class="form-control" name="record_status">
					<option value="">All Record Status</option>
					<option value="Pending" @selected(($filters['record_status'] ?? null) === 'Pending')>Pending</option>
					@foreach ($recordStatuses as $status)<option value="{{ $status }}" @selected(($filters['record_status'] ?? null) === $status)>{{ $status }}</option>@endforeach
				</select>
			</div>
			<div class="attendance-filter-field attendance-filter-field-full">
				<label>Search</label>
				<input class="form-control" type="search" name="search" placeholder="Search session or athlete..." value="{{ $filters['search'] ?? '' }}">
			</div>
		</div>
		<div class="attendance-filter-actions">
			<button class="button" type="submit">Apply</button>
			<a class="button button-muted" href="{{ route('admin.attendance') }}">Reset</a>
		</div>
	</form>
</section>

<!-- Attendance sessions table ----------------------------------------------- -->
<section class="attendance-sessions-card" aria-label="Attendance sessions">
	<header class="attendance-sessions-head">
		<div>
			<span class="attendance-sessions-kicker">Sessions</span>
			<h2 class="attendance-sessions-title">Attendance Sessions</h2>
			<p class="attendance-sessions-subtitle">Sessions reference a sport; rosters follow the athlete sport assignment.</p>
		</div>
	</header>

	<div class="table-wrap attendance-table-wrap">
		<table class="data-table attendance-table">
			<thead>
				<tr>
					<th style="width:12%">Date &amp; Time</th>
					<th style="width:13%">Session</th>
					<th style="width:13%">Event</th>
					<th style="width:8%">Sport</th>
					<th style="width:7%">Venue</th>
					<th style="width:5%" class="num">Total</th>
					<th style="width:6%" class="num">Present</th>
					<th style="width:5%" class="num">Pending</th>
					<th style="width:7%">Status</th>
					<th style="width:24%" class="actions-col">Actions</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($sessions as $session)
					@php($counts = $tally($session))
					<tr>
						<td>
							<strong>{{ $session->session_date?->format('M j, Y') }}</strong><br>
							<span class="attendance-session-time">
								{{ $session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') : 'Time not set' }}
								{{ $session->end_time ? ' &middot; '.\Illuminate\Support\Carbon::parse($session->end_time)->format('g:i A') : '' }}
							</span>
						</td>
						<td>
							<strong class="attendance-session-title">{{ $session->displayName() }}</strong>
						</td>
						<td>
							@if ($session->event)
								<a class="attendance-session-event" href="{{ route('events.show', $session->event) }}">{{ $session->event->title }}</a>
							@else
								<span class="attendance-session-event is-empty">Standalone session</span>
							@endif
						</td>
						<td>{{ $session->effectiveSportName() }}</td>
						<td>{{ $session->venue ?: '—' }}</td>
						<td class="num">{{ $counts['total'] }}</td>
						<td class="num">{{ $counts['present'] }}@if ($counts['late'])<span class="attendance-session-late">+{{ $counts['late'] }} late</span>@endif</td>
						<td class="num">{{ $counts['pending'] }}</td>
						<td>
							<span class="badge {{ $session->status === 'Open' ? 'status-present' : ($session->status === 'Cancelled' ? 'status-absent' : 'status-pending') }}">{{ $session->status }}</span>
						</td>
						<td class="actions-col">
							<div class="attendance-actions">
								<a class="attendance-action view" href="{{ route('admin.attendance', array_merge(request()->query(), ['session_id' => $session->id])) }}#attendance-session-detail">
									<svg class="attendance-action-icon" aria-hidden="true"><use href="#icon-eye"></use></svg>
									View
								</a>
								<form method="POST" action="{{ route('admin.attendance.sessions.sync', $session) }}">
									@csrf
									@method('PATCH')
									<button class="attendance-action sync" type="submit">
										<svg class="attendance-action-icon" aria-hidden="true"><use href="#icon-clipboard"></use></svg>
										Sync Roster
									</button>
								</form>
								@if ($session->status === 'Open')
									<form method="POST" action="{{ route('admin.attendance.sessions.close', $session) }}">
										@csrf
										@method('PATCH')
										<button class="attendance-action close" type="submit">Close Attendance</button>
									</form>
								@elseif ($session->status !== 'Open' && $session->status !== 'Cancelled')
									<form method="POST" action="{{ route('admin.attendance.sessions.open', $session) }}">
										@csrf
										@method('PATCH')
										<button class="attendance-action view" type="submit">Open</button>
									</form>
								@endif
								<a class="attendance-action export" href="{{ route('admin.attendance.sessions.export', $session) }}">
									<svg class="attendance-action-icon" aria-hidden="true"><use href="#icon-download"></use></svg>
									Export CSV
								</a>
							</div>
						</td>
					</tr>
				@empty
					<tr>
						<td colspan="10" class="empty-cell">No attendance sessions match your filters.</td>
					</tr>
				@endforelse
			</tbody>
		</table>
	</div>
</section>

<!-- Selected session detail ------------------------------------------------- -->
@if ($selected)
	<section class="attendance-session-detail-card" id="attendance-session-detail" aria-labelledby="session-detail-heading">
		<header class="attendance-session-detail-head">
			<div class="attendance-session-detail-copy">
				<h2 class="attendance-session-detail-title" id="session-detail-heading">{{ $selected->displayName() }}</h2>
				<p class="attendance-session-detail-meta">
					{{ $selected->session_date?->format('F j, Y') }} &middot;
					{{ $selected->start_time ? \Illuminate\Support\Carbon::parse($selected->start_time)->format('g:i A') : 'Time not set' }}
					@if ($selected->end_time) - {{ \Illuminate\Support\Carbon::parse($selected->end_time)->format('g:i A') }} @endif
					&middot; {{ $selected->venue ?: 'Venue not set' }} &middot; {{ $selected->effectiveSportName() }}
				</p>
				@if ($selected->description)<p class="attendance-session-detail-desc">{{ $selected->description }}</p>@endif
			</div>
			<span class="badge {{ $selected->status === 'Open' ? 'status-present' : ($selected->status === 'Cancelled' ? 'status-absent' : 'status-pending') }}">{{ $selected->status }}</span>
		</header>

		<div class="attendance-session-detail-stats">
			@php($selectedTally = ['total' => $records->count(), 'present' => $records->where('status', 'Present')->count(), 'late' => $records->where('status', 'Late')->count(), 'pending' => $records->where('status', 'Pending')->count(), 'absent' => $records->where('status', 'Absent')->count(), 'excused' => $records->where('status', 'Excused')->count()])
			<div class="attendance-session-stat">
				<span class="attendance-session-stat-label">Total</span>
				<strong class="attendance-session-stat-value">{{ $selectedTally['total'] }}</strong>
			</div>
			<div class="attendance-session-stat">
				<span class="attendance-session-stat-label">Present</span>
				<strong class="attendance-session-stat-value">{{ $selectedTally['present'] }}</strong>
			</div>
			<div class="attendance-session-stat">
				<span class="attendance-session-stat-label">Late</span>
				<strong class="attendance-session-stat-value">{{ $selectedTally['late'] }}</strong>
			</div>
			<div class="attendance-session-stat">
				<span class="attendance-session-stat-label">Pending</span>
				<strong class="attendance-session-stat-value">{{ $selectedTally['pending'] }}</strong>
			</div>
			<div class="attendance-session-stat">
				<span class="attendance-session-stat-label">Absent</span>
				<strong class="attendance-session-stat-value">{{ $selectedTally['absent'] }}</strong>
			</div>
			<div class="attendance-session-stat">
				<span class="attendance-session-stat-label">Excused</span>
				<strong class="attendance-session-stat-value">{{ $selectedTally['excused'] }}</strong>
			</div>
		</div>

		<div class="attendance-session-detail-actions">
			@if ($selected->status === 'Open')
				<form method="POST" action="{{ route('admin.attendance.sessions.close', $selected) }}">@csrf @method('PATCH')<button class="button" type="submit">Close Attendance</button></form>
			@elseif ($selected->status === 'Closed')
				<form method="POST" action="{{ route('admin.attendance.sessions.open', $selected) }}">@csrf @method('PATCH')<button class="button" type="submit">Reopen Attendance</button></form>
			@endif
			@if ($selected->status !== 'Cancelled')
				<form method="POST" action="{{ route('admin.attendance.sessions.cancel', $selected) }}">@csrf @method('PATCH')<button class="button button-muted" type="submit">Cancel Session</button></form>
			@endif
			<a class="button button-secondary" href="{{ route('admin.attendance.sessions.export.pdf', $selected) }}">Download PDF</a>
			<a class="button button-secondary" href="{{ route('admin.attendance.sessions.export', $selected) }}">Export CSV</a>
			<button class="button button-danger" type="button" data-confirm-dialog data-confirm-title="Delete Attendance Session?" data-confirm-message="Delete this attendance session and all of its records?" data-confirm-label="Delete Session" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.attendance.sessions.destroy', $selected) }}">Delete Session</button>
		</div>

		<div class="table-wrap attendance-roster-wrap">
			<table class="data-table attendance-roster-table">
				<thead>
					<tr>
						<th style="width:26%">Athlete</th>
						<th style="width:14%">Student ID</th>
						<th style="width:14%">Sport</th>
						<th style="width:14%">Status</th>
						<th style="width:14%">Check-in</th>
						<th>Correct</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($records as $record)
						<tr>
							<td>{{ $record->athlete?->name }}</td>
							<td>{{ $record->athlete?->student_id ?: 'No ID' }}</td>
							<td>{{ $record->athlete?->sport?->name ?? 'Unassigned' }}</td>
							<td><span class="badge {{ $record->status === 'Present' ? 'status-present' : ($record->status === 'Absent' ? 'status-absent' : ($record->status === 'Pending' ? 'status-pending' : 'status-cleared')) }}">{{ $record->status }}</span></td>
							<td>{{ $record->check_in_time?->format('g:i A') ?? '—' }}</td>
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
						<tr><td colspan="6" class="empty-cell">No athletes are assigned to this session&rsquo;s sport yet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</section>
@endif

<!-- Attendance history ------------------------------------------------------ -->
<section class="attendance-history-card" aria-label="Attendance history">
	<header class="attendance-history-head">
		<div>
			<span class="attendance-history-kicker">History</span>
			<h2 class="attendance-history-title">Attendance History</h2>
			<p class="attendance-history-subtitle">All session and event attendance records matching your filters.</p>
		</div>
	</header>

	<div class="table-wrap attendance-history-wrap">
		<table class="data-table attendance-history-table">
			<thead>
				<tr>
					<th style="width:14%">Date</th>
					<th style="width:22%">Session</th>
					<th style="width:20%">Athlete</th>
					<th style="width:14%">Sport</th>
					<th style="width:16%">Venue</th>
					<th style="width:12%">Status</th>
					<th style="width:12%">Check-in</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($attendance ?? [] as $row)
					<tr>
						<td>{{ $row->attended_on?->format('M j, Y') }}</td>
						<td>{{ $row->displayName() }}</td>
						<td>{{ $row->athlete?->name }}</td>
						<td>{{ $row->sportName() }}</td>
						<td>{{ $row->venue() ?: '—' }}</td>
						<td><span class="badge">{{ $row->status }}</span></td>
						<td>{{ $row->check_in_time?->format('g:i A') ?? '—' }}</td>
					</tr>
				@empty
					<tr><td colspan="7" class="empty-cell">No attendance records match your filters.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>

	@if ($attendance instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $attendance->total() > 0)
		<div class="attendance-history-pagination">
			{{ $attendance->appends(request()->except('history_page'))->links() }}
		</div>
	@endif
</section>
