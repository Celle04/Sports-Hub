<div class="grid report-summary-grid">
	<div class="card stat"><small>Total Athletes</small><strong>{{ $reportStats['athletes'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Total Sports</small><strong>{{ $reportStats['sports'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Total Events</small><strong>{{ $reportStats['events'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Attendance Records</small><strong>{{ $reportStats['attendance'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Attendance Rate</small><strong>{{ $reportStats['rate'] ?? 0 }}%</strong></div>
</div>

<section class="card report-filter-panel">
	<h2>Report Filters</h2>
	<form method="GET" action="{{ route('reports.index') }}" class="report-filters">
		<select class="form-control" name="report_type"><option value="attendance">Attendance Report</option><option value="athletes">Athlete Report</option><option value="sports">Sports Report</option><option value="events">Event Report</option><option value="applications">Application Report</option></select>
		<input class="form-control" type="date" name="from" value="{{ request('from') }}">
		<input class="form-control" type="date" name="to" value="{{ request('to') }}">
		<select class="form-control" name="sport_id"><option value="">All Sports</option>@foreach ($sports ?? [] as $sport)<option value="{{ $sport->id }}">{{ $sport->name }}</option>@endforeach</select>
		<button class="button" type="submit">Apply Filters</button>
		<a class="button button-secondary" href="{{ route('reports.index') }}">Reset</a>
	</form>
</section>

<section class="card report-data-panel">
	<div class="section-heading">
		<div><h2>{{ ucfirst($reportType ?? 'attendance') }} Report</h2><p class="meta">Attendance rate counts Present and Late only. Pending and cancelled sessions are excluded.</p></div>
		<a class="button button-secondary" href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}">Export CSV</a>
	</div>
	<div class="table-wrap"><table class="data-table report-table">
		@if (($reportType ?? 'attendance') === 'attendance')
			<thead><tr><th>Date</th><th>Athlete</th><th>Sport</th><th>Session</th><th>Status</th><th>Check-in</th><th>Rate</th></tr></thead>
			<tbody>
			@forelse ($reportData ?? [] as $row)
				<tr><td>{{ $row->attended_on?->format('M j, Y') }}</td><td>{{ $row->athlete?->name }}</td><td>{{ $row->sportName() }}</td><td>{{ $row->displayName() }}</td><td><span class="badge">{{ $row->status }}</span></td><td>{{ $row->check_in_time?->format('g:i A') ?? '--' }}</td><td>{{ $row->report_rate }}%</td></tr>
			@empty
				<tr><td colspan="7" class="empty-cell">No report data available.</td></tr>
			@endforelse
			</tbody>
		@elseif (($reportType ?? '') === 'athletes')
			<thead><tr><th>Athlete</th><th>Student ID</th><th>Sessions</th><th>Present</th><th>Absent</th><th>Rate</th></tr></thead>
			<tbody>
			@forelse ($reportData ?? [] as $row)
				<tr><td>{{ $row->name }}</td><td>{{ $row->student_id ?: 'No ID' }}</td><td>{{ $row->report_total }}</td><td>{{ $row->report_present }}</td><td>{{ $row->report_absent }}</td><td>{{ $row->report_rate }}%</td></tr>
			@empty
				<tr><td colspan="6" class="empty-cell">No athlete data available.</td></tr>
			@endforelse
			</tbody>
		@elseif (($reportType ?? '') === 'sports')
			<thead><tr><th>Sport</th><th>Athletes</th><th>Coaches</th><th>Events</th><th>Attendance Rate</th></tr></thead>
			<tbody>
			@forelse ($reportData ?? [] as $row)
				<tr><td>{{ $row->name }}</td><td>{{ $row->athlete_count }}</td><td>{{ $row->coaches_count }}</td><td>{{ $row->events_count }}</td><td>{{ $row->report_rate }}%</td></tr>
			@empty
				<tr><td colspan="5" class="empty-cell">No sports data available.</td></tr>
			@endforelse
			</tbody>
		@elseif (($reportType ?? '') === 'events')
			<thead><tr><th>Event</th><th>Sport</th><th>Date</th><th>Athletes</th><th>Present</th><th>Absent</th></tr></thead>
			<tbody>
			@forelse ($reportData ?? [] as $row)
				<tr><td>{{ $row->title }}</td><td>{{ $row->sport?->name ?? 'All Sports' }}</td><td>{{ $row->starts_at?->format('M j, Y') }}</td><td>{{ $row->report_athletes }}</td><td>{{ $row->report_present }}</td><td>{{ $row->report_absent }}</td></tr>
			@empty
				<tr><td colspan="6" class="empty-cell">No event data available.</td></tr>
			@endforelse
			</tbody>
		@else
			<thead><tr><th>Applicant</th><th>Student ID</th><th>Sport</th><th>Status</th><th>Submitted</th></tr></thead>
			<tbody>
			@forelse ($reportData ?? [] as $row)
				<tr><td>{{ $row->name }}</td><td>{{ $row->student_id ?: 'No ID' }}</td><td>{{ $row->sportCategory?->name ?? $row->sport ?? 'Unassigned' }}</td><td><span class="badge">{{ $row->status }}</span></td><td>{{ $row->created_at?->format('M j, Y') }}</td></tr>
			@empty
				<tr><td colspan="5" class="empty-cell">No application data available.</td></tr>
			@endforelse
			</tbody>
		@endif
	</table></div>
	@if (($reportData ?? null)?->hasPages()) {{ $reportData->links() }} @endif
</section>
