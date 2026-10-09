@php($largestSportAthletes = max(1, (int) $athletesBySport->max('athletes_count')))

@if (($attentionItems ?? collect())->isNotEmpty())
	<section class="card dashboard-attention">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">ACTION REQUIRED</span>
				<h2>Needs Your Attention</h2>
			</div>
			<span class="badge status-pending">{{ $attentionItems->count() }} item{{ $attentionItems->count() === 1 ? '' : 's' }}</span>
		</div>
		@foreach ($attentionItems as $item)
			<a class="attention-item attention-{{ $item['tone'] }}" href="{{ route($item['route']) }}">
				<span><svg aria-hidden="true"><use href="#icon-{{ $item['tone'] === 'danger' ? 'medical' : 'bell' }}"></use></svg></span>
				<strong>{{ $item['message'] }}</strong>
				<em>{{ $item['action'] }} <span aria-hidden="true">&rarr;</span></em>
			</a>
		@endforeach
	</section>
@endif

<div class="grid admin-dashboard-stats">
	@foreach ($stats as $stat)
		<a class="card admin-dashboard-stat" href="{{ route($stat['route']) }}">
			<span class="admin-dashboard-stat-body">
				<small>{{ $stat['label'] }}</small>
				<strong>{{ $stat['value'] }}</strong>
				<span class="dashboard-stat-action">{{ $stat['action'] }}</span>
			</span>
			<span class="admin-dashboard-icon admin-dashboard-icon-{{ $stat['tone'] }}"><svg aria-hidden="true"><use href="#icon-{{ $stat['icon'] }}"></use></svg></span>
		</a>
	@endforeach
</div>

{{-- ========================================================================
	 SPORTS STATISTICS & ACHIEVEMENTS
	 ======================================================================== --}}
<section class="sports-statistics-section">
	<div class="section-heading">
		<div>
			<span class="admin-dashboard-kicker">SCHOOL-WIDE OVERVIEW</span>
			<h2>Sports Statistics &amp; Achievements</h2>
			<p>Live counts across {{ $sportsStats['sports'] }} program{{ $sportsStats['sports'] === 1 ? '' : 's' }} and every recorded achievement</p>
		</div>
		<div class="sports-statistics-actions">
			<a class="button button-secondary" href="{{ route('admin.achievements.certificate.generate') }}">Generate Certificate</a>
			<a class="text-link" href="{{ route('admin.achievements') }}">View All Achievements <span aria-hidden="true">&rarr;</span></a>
		</div>
	</div>

	<div class="grid sports-stat-grid">
		<div class="card stat sports-stat-tile"><small>Total Athletes</small><strong>{{ $sportsStats['athletes'] }}</strong></div>
		<div class="card stat sports-stat-tile"><small>Total Sports</small><strong>{{ $sportsStats['sports'] }}</strong></div>
		<div class="card stat sports-stat-tile"><small>Total Achievements</small><strong>{{ $sportsStats['achievements'] }}</strong></div>
		<div class="card stat sports-stat-tile"><small>Gold Medals</small><strong>{{ $sportsStats['gold'] }}</strong></div>
		<div class="card stat sports-stat-tile"><small>Silver Medals</small><strong>{{ $sportsStats['silver'] }}</strong></div>
		<div class="card stat sports-stat-tile"><small>Bronze Medals</small><strong>{{ $sportsStats['bronze'] }}</strong></div>
	</div>

	<div class="dashboard-chart-grid">
		<section class="card dashboard-chart-card">
			<div class="section-heading">
				<div>
					<span class="admin-dashboard-kicker">PROGRAM DISTRIBUTION</span>
					<h2>Athletes by Sport</h2>
					<p>{{ $athletesBySport->sum('athletes_count') }} athlete{{ $athletesBySport->sum('athletes_count') === 1 ? '' : 's' }} assigned to {{ $athletesBySport->count() }} program{{ $athletesBySport->count() === 1 ? '' : 's' }}</p>
				</div>
			</div>
			@php($largestSportAthletes = max(1, (int) $athletesBySport->max('athletes_count')))
			<div class="dashboard-chart">
				@forelse ($athletesBySport as $sport)
					<div class="dashboard-chart-row">
						<div class="dashboard-chart-label"><span>{{ $sport->name }}</span><strong>{{ $sport->athletes_count }}</strong></div>
						<div class="progress-track" role="progressbar" aria-label="{{ $sport->name }} athlete count" aria-valuemin="0" aria-valuemax="{{ $largestSportAthletes }}" aria-valuenow="{{ $sport->athletes_count }}"><span style="width: {{ round($sport->athletes_count / $largestSportAthletes * 100) }}%"></span></div>
					</div>
				@empty
					<p class="empty-state">No athletes have been registered yet.</p>
				@endforelse
			</div>
		</section>

		<section class="card dashboard-chart-card">
			<div class="section-heading">
				<div>
					<span class="admin-dashboard-kicker">ACHIEVEMENT MIX</span>
					<h2>Achievements by Sport</h2>
					<p>{{ $achievementsBySport->sum('total') }} achievement{{ $achievementsBySport->sum('total') === 1 ? '' : 's' }} split across {{ $achievementsBySport->count() }} sport{{ $achievementsBySport->count() === 1 ? '' : 's' }}</p>
				</div>
			</div>
			@php($largestSportAchievements = max(1, (int) $achievementsBySport->max('total')))
			<div class="dashboard-chart">
				@forelse ($achievementsBySport as $record)
					<div class="dashboard-chart-row">
						<div class="dashboard-chart-label"><span>{{ $record->sport?->name ?? 'Unknown sport' }}</span><strong>{{ $record->total }}</strong></div>
						<div class="progress-track" role="progressbar" aria-label="{{ $record->sport?->name ?? 'Sport' }} achievement count" aria-valuemin="0" aria-valuemax="{{ $largestSportAchievements }}" aria-valuenow="{{ $record->total }}"><span style="width: {{ round($record->total / $largestSportAchievements * 100) }}%"></span></div>
					</div>
				@empty
					<p class="empty-state">No achievements have been recorded yet.</p>
				@endforelse
			</div>
		</section>
	</div>

	<div class="dashboard-chart-grid">
		<section class="card dashboard-chart-card">
			<div class="section-heading">
				<div>
					<span class="admin-dashboard-kicker">MEDAL STANDINGS</span>
					<h2>Achievement Medal Summary</h2>
					<p>{{ array_sum([$sportsStats['gold'], $sportsStats['silver'], $sportsStats['bronze']]) }} medal{{ array_sum([$sportsStats['gold'], $sportsStats['silver'], $sportsStats['bronze']]) === 1 ? '' : 's' }} awarded to date</p>
				</div>
			</div>
			@if (array_sum([$sportsStats['gold'], $sportsStats['silver'], $sportsStats['bronze']]) > 0)
				@php($medalTotal = array_sum([$sportsStats['gold'], $sportsStats['silver'], $sportsStats['bronze']]))
				<div class="medal-summary-list">
					<div class="medal-summary-row">
						<span class="badge achievement-medal-gold-medal">Gold Medal</span>
						<div class="progress-track medal-track"><span class="medal-track-gold" style="width: {{ round($sportsStats['gold'] / $medalTotal * 100) }}%"></span></div>
						<strong>{{ $sportsStats['gold'] }}</strong>
					</div>
					<div class="medal-summary-row">
						<span class="badge achievement-medal-silver-medal">Silver Medal</span>
						<div class="progress-track medal-track"><span class="medal-track-silver" style="width: {{ round($sportsStats['silver'] / $medalTotal * 100) }}%"></span></div>
						<strong>{{ $sportsStats['silver'] }}</strong>
					</div>
					<div class="medal-summary-row">
						<span class="badge achievement-medal-bronze-medal">Bronze Medal</span>
						<div class="progress-track medal-track"><span class="medal-track-bronze" style="width: {{ round($sportsStats['bronze'] / $medalTotal * 100) }}%"></span></div>
						<strong>{{ $sportsStats['bronze'] }}</strong>
					</div>
				</div>
			@else
				<p class="empty-state">No achievements have been recorded yet.</p>
			@endif
		</section>

		<section class="card dashboard-chart-card">
			<div class="section-heading">
				<div>
					<span class="admin-dashboard-kicker">LEADERBOARD</span>
					<h2>Top Performing Sports</h2>
					<p>Ranked by total achievements</p>
				</div>
			</div>
			@if ($achievementsBySport->isNotEmpty())
				<ol class="top-sports-list">
					@foreach ($achievementsBySport->take(5) as $rank => $record)
						<li>
							<span class="top-sports-rank">{{ $rank + 1 }}</span>
							<span class="top-sports-name">{{ $record->sport?->name ?? 'Unknown sport' }}</span>
							<strong>{{ $record->total }} achievement{{ $record->total === 1 ? '' : 's' }}</strong>
						</li>
					@endforeach
				</ol>
			@else
				<p class="empty-state">No achievements have been recorded yet.</p>
			@endif
		</section>
	</div>

	<section class="card dashboard-recent-achievements">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">LATEST WINS</span>
				<h2>Recent Achievements</h2>
				<p>The {{ $recentAchievements->count() }} most recent recorded achievement{{ $recentAchievements->count() === 1 ? '' : 's' }}</p>
			</div>
			<a class="text-link" href="{{ route('admin.achievements') }}">View All Achievements <span aria-hidden="true">&rarr;</span></a>
		</div>
		@if ($recentAchievements->isNotEmpty())
			<div class="table-wrap">
				<table class="data-table">
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
								<td><a class="text-link" href="{{ route('admin.achievements.certificate.print', $record) }}">Certificate</a></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@else
			<p class="empty-state">No achievements have been recorded yet.</p>
		@endif
	</section>
</section>

<div class="dashboard-two-column">
	<section class="card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">WHAT'S NEXT</span>
				<h2>Upcoming Events</h2>
				<p>{{ $upcomingEvents->count() }} scheduled event{{ $upcomingEvents->count() === 1 ? '' : 's' }} on record</p>
			</div>
			<a class="text-link" href="{{ route('events.index') }}">All events <span aria-hidden="true">&rarr;</span></a>
		</div>
		@forelse ($upcomingEvents as $event)
			<article class="admin-dashboard-event">
				<div class="admin-dashboard-event-date">{{ $event->starts_at->format('M') }}<strong>{{ $event->starts_at->format('j') }}</strong>{{ $event->starts_at->format('Y') }}</div>
				<div class="admin-dashboard-event-body">
					<h3>{{ $event->title }}</h3>
					<p>{{ $event->starts_at->format('l, g:i A') }}@if ($event->ends_at)&nbsp;&ndash;&nbsp;{{ $event->ends_at->format('g:i A') }}@endif</p>
					<p class="meta">{{ $event->sport?->name ?? 'All sports' }} &middot; {{ $event->venue ?: 'Venue not set' }}</p>
				</div>
			</article>
		@empty
			<p class="empty-state">No upcoming events are scheduled.</p>
		@endforelse
	</section>

	<section class="card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">PROGRAM MIX</span>
				<h2>Athletes by Sport</h2>
				<p>{{ $athletesBySport->sum('athletes_count') }} athlete{{ $athletesBySport->sum('athletes_count') === 1 ? '' : 's' }} across {{ $athletesBySport->count() }} program{{ $athletesBySport->count() === 1 ? '' : 's' }}</p>
			</div>
			<a class="text-link" href="{{ route('athletes.index') }}">Roster <span aria-hidden="true">&rarr;</span></a>
		</div>
		@forelse ($athletesBySport as $sport)
			<div class="sport-progress">
				<div><span>{{ $sport->name }}</span><strong>{{ $sport->athletes_count }}</strong></div>
				<div class="progress-track" role="progressbar" aria-label="{{ $sport->name }} athlete share" aria-valuemin="0" aria-valuemax="{{ $largestSportAthletes }}" aria-valuenow="{{ $sport->athletes_count }}"><span style="width: {{ round($sport->athletes_count / $largestSportAthletes * 100) }}%"></span></div>
			</div>
		@empty
			<p class="empty-state">No sports programs have been created yet.</p>
		@endforelse
	</section>
</div>

<div class="dashboard-two-column">
	<section class="card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">REVIEW QUEUE</span>
				<h2>Pending Sports Applications</h2>
				<p>Submitted applications waiting for a decision</p>
			</div>
			<a class="text-link" href="{{ route('admin.applications') }}">Review all <span aria-hidden="true">&rarr;</span></a>
		</div>
		@if (($pendingApplications ?? collect())->isNotEmpty())
			<div class="table-wrap">
				<table class="data-table">
					<thead><tr><th>Athlete</th><th>Category</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
					<tbody>
						@foreach ($pendingApplications as $application)
							<tr>
								<td><strong>{{ $application->name }}</strong></td>
								<td>{{ $application->sportCategory?->name ?? $application->sport ?? 'Not specified' }}</td>
								<td>{{ $application->submitted_at?->format('M j, Y') ?? 'Not recorded' }}</td>
								<td><span class="badge status-pending">{{ $application->status }}</span></td>
								<td><a class="text-link" href="{{ route('admin.applications') }}">Review</a></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@else
			<p class="empty-state">There are no pending applications right now.</p>
		@endif
	</section>

	<section class="card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">HEALTH MONITORING</span>
				<h2>Medical Clearance</h2>
				<p>Athlete clearance status breakdown</p>
			</div>
			<a class="text-link" href="{{ route('admin.medical') }}">Records <span aria-hidden="true">&rarr;</span></a>
		</div>
		<div class="medical-summary">
			@foreach (['Cleared' => 'cleared-dot', 'Pending' => 'pending-dot', 'Not Cleared' => 'not-cleared-dot', 'Restricted' => 'restricted-dot'] as $status => $dotClass)
				<div>
					<span class="status-dot {{ $dotClass }}"></span>
					<span>{{ $status }}</span>
					<strong>{{ $medicalStatusCounts[$status] ?? 0 }}</strong>
				</div>
			@endforeach
		</div>
	</section>
</div>

<div class="dashboard-two-column">
	<section class="card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">LATEST CHANGES</span>
				<h2>Recent Activity</h2>
				<p>The most recent updates across the hub</p>
			</div>
		</div>
		@forelse ($recentActivity as $activity)
			<div class="admin-dashboard-activity">
				<span></span>
				<div>
					<p>{{ $activity['label'] }}</p>
					<small>{{ $activity['date']?->diffForHumans() ?? 'Recently' }}</small>
				</div>
			</div>
		@empty
			<p class="empty-state">No activity has been recorded yet.</p>
		@endforelse
	</section>

	<section class="card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">SHORTCUTS</span>
				<h2>Quick Actions</h2>
				<p>Jump straight into the module you need</p>
			</div>
		</div>
		<div class="admin-dashboard-quick-actions">
			@php($quickActions = [
				['label' => 'Create Event', 'hint' => 'Schedule a session or competition', 'route' => 'events.create', 'icon' => 'calendar', 'tone' => 'red'],
				['label' => 'Review Applications', 'hint' => 'Decide on submitted applications', 'route' => 'admin.applications', 'icon' => 'clipboard', 'tone' => 'gold'],
				['label' => 'Manage Athletes', 'hint' => 'View and edit athlete accounts', 'route' => 'athletes.index', 'icon' => 'users', 'tone' => 'green'],
				['label' => 'Take Attendance', 'hint' => 'Open sessions and record attendance', 'route' => 'admin.attendance', 'icon' => 'activity', 'tone' => 'red'],
				['label' => 'Post Announcement', 'hint' => 'Notify athletes and coaches', 'route' => 'admin.announcements', 'icon' => 'megaphone', 'tone' => 'green'],
				['label' => 'Record Achievement', 'hint' => 'Award a medal or certificate', 'route' => 'admin.achievements', 'icon' => 'trophy', 'tone' => 'gold'],
				['label' => 'Medical Records', 'hint' => 'Manage clearances and checkups', 'route' => 'admin.medical', 'icon' => 'medical', 'tone' => 'gold'],
				['label' => 'Manage Sports', 'hint' => 'Add or edit sports programs', 'route' => 'sports.create', 'icon' => 'trophy', 'tone' => 'green'],
				['label' => 'Reports', 'hint' => 'Generate attendance and roster reports', 'route' => 'reports.index', 'icon' => 'chart', 'tone' => 'red'],
			])
			@foreach ($quickActions as $action)
				<a class="admin-dashboard-action" href="{{ route($action['route']) }}">
					<span class="admin-dashboard-icon admin-dashboard-icon-{{ $action['tone'] }}"><svg aria-hidden="true"><use href="#icon-{{ $action['icon'] }}"></use></svg></span>
					<span><strong>{{ $action['label'] }}</strong><small>{{ $action['hint'] }}</small></span>
				</a>
			@endforeach
		</div>
	</section>
</div>