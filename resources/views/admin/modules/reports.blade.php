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
	<h2>{{ ucfirst($reportType ?? 'attendance') }} Report</h2>
	<div class="table-wrap"><table class="data-table report-table"><thead><tr><th>Date</th><th>Athlete</th><th>Sport</th><th>Event</th><th>Status</th></tr></thead><tbody>
		@forelse ($reportData ?? [] as $row)
			<tr><td>{{ $row->attended_on?->format('M j, Y') }}</td><td>{{ $row->athlete?->name }}</td><td>{{ $row->athlete?->sport?->name ?? 'Unassigned' }}</td><td>{{ $row->event?->title }}</td><td><span class="badge">{{ $row->status }}</span></td></tr>
		@empty
			<tr><td colspan="5" class="empty-cell">No report data available.</td></tr>
		@endforelse
	</tbody></table></div>
	@if (($reportData ?? null)?->hasPages()) {{ $reportData->links() }} @endif
</section>
