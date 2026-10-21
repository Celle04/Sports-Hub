@php
	$choices = [
		['key' => 'athletes', 'label' => 'Athlete List', 'hint' => 'Complete roster with sport, coach and profile details'],
		['key' => 'attendance', 'label' => 'Attendance Records', 'hint' => 'Every matching record with status breakdown and rate'],
		['key' => 'athlete-summary', 'label' => 'Athlete Attendance Summary', 'hint' => 'Per-athlete totals and attendance rate'],
		['key' => 'coaches', 'label' => 'Coach List', 'hint' => 'All coaches with sport, type and specialization'],
	];
	$payload = $reportPayload ?? null;
	$columns = $payload['columns'] ?? [];
	$rows = $payload['rows'] ?? collect();
	$options = $filterOptions ?? [];
	$badgeClass = function ($status) {
		return match ($status) {
			'Present', 'Active' => 'status-present',
			'Absent', 'Inactive' => 'status-absent',
			'Late', 'Pending', 'Open' => 'status-pending',
			'Excused', 'Closed' => 'status-cleared',
			default => '',
		};
	};
@endphp

@if (! $report)
	<div class="grid report-summary-grid">
		@foreach ($overview ?? [] as $stat)
			<div class="card stat"><small>{{ $stat['label'] }}</small><strong>{{ $stat['value'] }}</strong></div>
		@endforeach
	</div>

	<section class="card report-filter-panel">
		<div class="section-heading">
			<div>
				<h2>Reporting Center</h2>
				<p class="meta">Every report is generated directly from the SportsHub database, complete and unpaginated. Choose a report to preview it, then print it or export it as PDF or CSV.</p>
			</div>
		</div>
		<div class="report-chooser">
			@foreach ($choices as $choice)
				<a class="report-chooser-item" href="{{ route('reports.index', ['report' => $choice['key']]) }}">
					<strong>{{ $choice['label'] }}</strong>
					<small>{{ $choice['hint'] }}</small>
				</a>
			@endforeach
		</div>
	</section>
@else
	<section class="card report-filter-panel">
		<div class="section-heading">
			<div>
				<h2>{{ $payload['title'] }}</h2>
				<p class="meta">Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }} by {{ $generatedBy }}</p>
			</div>
		</div>

		<form method="GET" action="{{ route('reports.index') }}" class="report-filters">
			<div class="form-group">
				<label for="report-picker">Report</label>
				<select class="form-control" id="report-picker" name="report">
					@foreach ($choices as $choice)
						<option value="{{ $choice['key'] }}" @selected($report === $choice['key'])>{{ $choice['label'] }}</option>
					@endforeach
				</select>
			</div>

			@if (in_array($report, ['athletes', 'coaches'], true))
				<div class="form-group">
					<label for="report-search">Search</label>
					<input class="form-control" id="report-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, student ID, email...">
				</div>
			@endif

			<div class="form-group">
				<label for="report-sport">Sport</label>
				<select class="form-control" id="report-sport" name="sport_id">
					<option value="">All Sports</option>
					@foreach (($options['sports'] ?? collect()) as $sport)
						<option value="{{ $sport->id }}" @selected(($filters['sport_id'] ?? null) == $sport->id)>{{ $sport->name }}</option>
					@endforeach
				</select>
			</div>

			@if ($report === 'athletes')
				<div class="form-group">
					<label for="report-category">Category</label>
					<select class="form-control" id="report-category" name="category">
						@foreach (\App\Http\Controllers\ReportController::ATHLETE_CATEGORY_LABELS as $categoryKey => $categoryLabel)
							<option value="{{ $categoryKey }}" @selected(($filters['category'] ?? 'all') === $categoryKey)>{{ $categoryLabel }}</option>
						@endforeach
					</select>
					<small class="form-hint">The list and the download are filed under the same category.</small>
				</div>
				<div class="form-group">
					<label for="report-status">Status</label>
					<select class="form-control" id="report-status" name="status">
						<option value="">All Status</option>
						@foreach (($options['statuses'] ?? ['Active', 'Inactive']) as $status)
							<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="report-grade">Year Level</label>
					<select class="form-control" id="report-grade" name="grade">
						<option value="">All Year Levels</option>
						@foreach (($options['grades'] ?? collect()) as $grade)
							<option value="{{ $grade }}" @selected(($filters['grade'] ?? null) === (string) $grade)>{{ $grade }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="report-gender">Gender</label>
					<select class="form-control" id="report-gender" name="gender">
						<option value="">All</option>
						@foreach (($options['genders'] ?? collect()) as $gender)
							<option value="{{ $gender }}" @selected(($filters['gender'] ?? null) === (string) $gender)>{{ $gender }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="report-eligibility">Eligibility</label>
					<select class="form-control" id="report-eligibility" name="eligibility">
						<option value="">All</option>
						@foreach (['Eligible', 'Not Eligible', 'Pending'] as $state)
							<option value="{{ $state }}" @selected(($filters['eligibility'] ?? null) === $state)>{{ $state }}</option>
						@endforeach
					</select>
				</div>
			@endif

			@if ($report === 'coaches')
				<div class="form-group">
					<label for="report-coach-type">Coach Type</label>
					<select class="form-control" id="report-coach-type" name="coach_type">
						<option value="">All Coach Types</option>
						@foreach (($options['coachTypes'] ?? []) as $type)
							<option value="{{ $type }}" @selected(($filters['coach_type'] ?? null) === $type)>{{ $type }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="report-status">Status</label>
					<select class="form-control" id="report-status" name="status">
						<option value="">All Status</option>
						@foreach (($options['statuses'] ?? []) as $status)
							<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
						@endforeach
					</select>
				</div>
			@endif

			@if (in_array($report, ['attendance', 'athlete-summary'], true))
				<div class="form-group">
					<label for="report-event">Event</label>
					<select class="form-control" id="report-event" name="event_id">
						<option value="">All Events</option>
						@foreach (($options['events'] ?? []) as $event)
							<option value="{{ $event['id'] }}" @selected(($filters['event_id'] ?? null) == $event['id'])>{{ $event['label'] }}</option>
						@endforeach
					</select>
					<small class="form-hint">Pick an event to download the attendance of that event only.</small>
				</div>
				<div class="form-group">
					<label for="report-session">Attendance Session</label>
					<select class="form-control" id="report-session" name="session_id">
						<option value="">All Sessions</option>
						@foreach (($options['sessions'] ?? []) as $session)
							<option value="{{ $session['id'] }}" @selected(($filters['session_id'] ?? null) == $session['id'])>{{ $session['label'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="report-athlete">Athlete</label>
					<select class="form-control" id="report-athlete" name="athlete_id">
						<option value="">All Athletes</option>
						@foreach (($options['athletes'] ?? []) as $athlete)
							<option value="{{ $athlete['id'] }}" @selected(($filters['athlete_id'] ?? null) == $athlete['id'])>{{ $athlete['label'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="report-from">Date From</label>
					<input class="form-control" id="report-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
				</div>
				<div class="form-group">
					<label for="report-to">Date To</label>
					<input class="form-control" id="report-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
				</div>
			@endif

			@if ($report === 'attendance')
				<div class="form-group">
					<label for="report-status">Status</label>
					<select class="form-control" id="report-status" name="status">
						<option value="">All Statuses</option>
						@foreach (($options['recordStatuses'] ?? []) as $status)
							<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
						@endforeach
					</select>
				</div>
			@endif

			<div class="report-filter-actions">
				<button class="button" type="submit">Apply Filters</button>
				<a class="button button-secondary" href="{{ route('reports.index') }}">Reset</a>
			</div>
		</form>
	</section>

	@if ($report === 'athletes')
		@php($exportFilters = array_merge($filters, ['export_scope' => $exportScope]))
		<div class="report-export-panel">
			<div class="report-export-choice">
				<span class="report-export-label">Download the athlete list</span>
				<div class="report-export-options" role="group" aria-label="Export scope">
					<a class="report-export-option{{ $exportScope === 'all' ? ' is-active' : '' }}" href="{{ route('reports.index', array_merge(['report' => $report], $filters, ['export_scope' => 'all'])) }}">All athletes</a>
					<a class="report-export-option{{ $exportScope === 'sport' ? ' is-active' : '' }}" href="{{ route('reports.index', array_merge(['report' => $report], $filters, ['export_scope' => 'sport'])) }}">By sport</a>
				</div>
				<small class="form-hint">By sport files the list under a heading for every sport, so the download can be split up straight away.</small>
				@if ($exportScope === 'sport' && count($payload['groups'] ?? []) > 0)
					<ul class="report-export-sports">
						@foreach ($payload['groups'] as $group)
							<li>
								<span>{{ $group['label'] }}</span>
								<span class="meta">{{ $group['value'] }} athlete{{ $group['value'] === 1 ? '' : 's' }}</span>
								<a class="text-link" href="{{ route('reports.export', array_merge(['report' => $report, 'export_scope' => 'sport'], $group['id'] ? ['sport_id' => $group['id']] : [], ['format' => 'csv'])) }}">Download CSV</a>
							</li>
						@endforeach
					</ul>
				@endif
			</div>
			<div class="report-export-actions">
				<button class="button button-secondary" type="button" onclick="window.print()">Print</button>
				<a class="button button-secondary" href="{{ route('reports.export', array_merge(['report' => $report], $exportFilters, ['format' => 'csv'])) }}">Export CSV</a>
				<a class="button" href="{{ route('reports.export', array_merge(['report' => $report], $exportFilters, ['format' => 'pdf'])) }}">Export PDF</a>
				<a class="button button-muted" href="{{ route('reports.index') }}">All Reports</a>
			</div>
		</div>
	@else
		<div class="report-actions">
			<button class="button button-secondary" type="button" onclick="window.print()">Print</button>
			<a class="button button-secondary" href="{{ route('reports.export', array_merge(['report' => $report], $filters, ['format' => 'csv'])) }}">Export CSV</a>
			<a class="button" href="{{ route('reports.export', array_merge(['report' => $report], $filters, ['format' => 'pdf'])) }}">Export PDF</a>
			<a class="button button-muted" href="{{ route('reports.index') }}">All Reports</a>
		</div>
	@endif

	<div class="report-print-header">
		<p>SURIGAO DEL NORTE NATIONAL HIGH SCHOOL</p>
		<h1>SPORTSHUB</h1>
		<h2>{{ $payload['title'] }}</h2>
		<p>Generated {{ $generatedAt->format('F j, Y') }} by {{ $generatedBy }} &middot; Filters: {{ $payload['filtersLabel'] }}</p>
	</div>

	<div class="grid report-summary-grid">
		@foreach ($payload['summary'] as $item)
			<div class="card stat"><small>{{ $item['label'] }}</small><strong>{{ $item['value'] }}</strong></div>
		@endforeach
	</div>

	<section class="card report-data-panel">
		<div class="section-heading">
			<div>
				<h2>{{ $payload['title'] }}</h2>
				<p class="meta">{{ $rows->count() }} record(s) &middot; Filters: {{ $payload['filtersLabel'] }} &middot; Attendance rate counts Present and Late over counted records only. Pending rows and cancelled sessions are excluded.</p>
			</div>
		</div>
		<div class="table-wrap">
			<table class="data-table report-table">
				<thead>
					<tr>
						@foreach ($columns as $column)
							<th>{{ $column['label'] }}</th>
						@endforeach
					</tr>
				</thead>
				<tbody>
					@php($group = null)
					@forelse ($rows as $row)
						@if (($payload['grouped'] ?? false) && ($row['group'] ?? null) !== $group)
							@php($group = $row['group'] ?? null)
							<tr class="report-group-row"><td colspan="{{ count($columns) }}">{{ $group }}</td></tr>
						@endif
						<tr>
							@foreach ($columns as $column)
								<td>
									@if ($column['key'] === 'status')
										<span class="badge {{ $badgeClass($row[$column['key']] ?? '') }}">{{ $row[$column['key']] ?? '—' }}</span>
									@elseif ($column['key'] === 'rate')
										{{ $row[$column['key']] ?? 0 }}%
									@else
										{{ $row[$column['key']] ?? '—' }}
									@endif
								</td>
							@endforeach
						</tr>
					@empty
						<tr><td colspan="{{ count($columns) }}" class="empty-cell">{{ $payload['emptyMessage'] }}</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</section>
@endif
