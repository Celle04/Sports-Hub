<section class="dashboard-athlete card" aria-label="Student-athlete profile summary">
	<div class="dashboard-avatar">
		@if ($athlete->profile_photo_path)
			<img src="{{ \\Illuminate\\Support\\Facades\\Storage::url($athlete->profile_photo_path) }}" alt="{{ $athlete->name }} profile photo">
		@else
			<span>{{ \\Illuminate\\Support\\Str::upper(\\Illuminate\\Support\\Str::substr($athlete->name, 0, 2)) }}</span>
		@endif
	</div>
	<div class="dashboard-athlete-name"><strong>{{ $athlete->name }}</strong><span>{{ $athlete->sport?->name ?? 'Sport not assigned' }}</span></div>
	<div class="dashboard-athlete-detail"><small>Student ID</small><strong>{{ $athlete->student_id ?: 'Not recorded' }}</strong></div>
	<div class="dashboard-athlete-detail"><small>Grade level</small><strong>{{ $studentApplication?->grade ?: 'Not recorded' }}</strong></div>
	<div class="dashboard-athlete-detail"><small>Athlete status</small><strong><span class="badge {{ $athlete->status === 'Active' ? 'status-present' : '' }}">{{ $athlete->status ?: 'Not recorded' }}</span></strong></div>
	<a class="button button-secondary dashboard-profile-link" href="{{ route('student.profile') }}">View My Profile</a>
</section>

<div class="student-dashboard">
	<nav class="student-dashboard-links" aria-label="Student quick links">
		<a class="card student-dashboard-link" href="{{ route('student.schedule') }}"><span class="student-dashboard-icon student-dashboard-icon-red"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span><span><small>STAY ON TRACK</small><strong>My Schedule</strong><span>{{ $dashboardUpcomingCount }} upcoming events</span></span></a>
		<a class="card student-dashboard-link" href="{{ route('student.attendance') }}"><span class="student-dashboard-icon student-dashboard-icon-green"><svg aria-hidden="true"><use href="#icon-chart"></use></svg></span><span><small>YOUR PROGRESS</small><strong>Attendance</strong><span>{{ $dashboardAttendanceCounts['percentage'] }}% attendance rate</span></span></a>
		<a class="card student-dashboard-link" href="{{ route('student.coach') }}"><span class="student-dashboard-icon student-dashboard-icon-gold"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span><span><small>YOUR TEAM</small><strong>Coach Info</strong><span>Connect with your coach</span></span></a>
	</nav>

	<section class="card student-dashboard-announcements">
		<div class="student-dashboard-heading"><div><span class="student-dashboard-kicker">FROM THE SPORTS HUB</span><h2>Latest announcements</h2></div><a class="text-link" href="{{ route('student.announcements') }}">All announcements</a></div>
		<div class="student-announcement-list">
			@forelse ($announcements->take(3) as $announcement)
				<article class="student-announcement"><div class="meta">{{ $announcement->published_at?->format('M j, Y') ?? 'Published' }}@if ($announcement->sport) &middot; {{ $announcement->sport->name }}@endif</div><h3>{{ $announcement->title }}</h3><p>{{ \\Illuminate\\Support\\Str::limit($announcement->body, 150) }}</p></article>
			@empty
				<p class="empty-state">No current announcements.</p>
			@endforelse
		</div>
	</section>

	<div class="student-dashboard-panels">
		<section class="card student-dashboard-events">
			<div class="student-dashboard-heading"><div><span class="student-dashboard-kicker">WHAT'S NEXT</span><h2>Upcoming activities</h2></div></div>
			@if ($dashboardNextActivity)
				<article class="student-event"><div class="student-event-date">{{ $dashboardNextActivity['startsAt']->format('M j') }}</div><div class="student-event-details"><strong>{{ $dashboardNextActivity['title'] }}</strong><p>{{ $dashboardNextActivity['startsAt']->format('l, g:i A') }}@if ($dashboardNextActivity['endsAt']) - {{ $dashboardNextActivity['endsAt']->format('g:i A') }}@endif &middot; {{ $dashboardNextActivity['venue'] ?: 'Venue not set' }}</p><p class="meta">{{ $dashboardNextActivity['sport'] }} &middot; Coach {{ $dashboardNextActivity['coach'] ?? 'Not assigned' }}</p></div></article>
			@else
				<p class="empty-state">No upcoming activities.</p>
			@endif
			<a class="text-link" href="{{ route('student.schedule') }}">View schedule <span aria-hidden="true">&rarr;</span></a>
		</section>

		<section class="card student-dashboard-attendance">
			<div class="student-dashboard-heading"><div><span class="student-dashboard-kicker">YOUR PROGRESS</span><h2>Attendance</h2></div></div>
			<div class="dashboard-attendance-percent">{{ $dashboardAttendanceCounts['percentage'] }}<span>%</span></div>
			<div class="progress-track dashboard-attendance-progress" role="progressbar" aria-label="Attendance percentage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $dashboardAttendanceCounts['percentage'] }}"><span style="width: {{ $dashboardAttendanceCounts['percentage'] }}%"></span></div>
			<div class="dashboard-attendance-counts"><div><strong>{{ $dashboardAttendanceCounts['present'] }}</strong><span>Present</span></div><div><strong>{{ $dashboardAttendanceCounts['late'] }}</strong><span>Late</span></div><div><strong>{{ $dashboardAttendanceCounts['absent'] }}</strong><span>Absent</span></div><div><strong>{{ $dashboardAttendanceCounts['excused'] }}</strong><span>Excused</span></div></div>
			@if (($dashboardUpcomingAttendance ?? collect())->isNotEmpty())
				<div class="dashboard-open-sessions"><strong class="dashboard-open-label"><span></span> UPCOMING ATTENDANCE</strong>
					@foreach ($dashboardUpcomingAttendance as $session)
						<div class="dashboard-open-session"><div><strong>{{ $session->displayName() }}</strong><small>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $session->venue ?: 'Venue not set' }}</small></div><span class="badge status-pending">Upcoming</span></div>
					@endforeach
				</div>
			@endif
			@if ($dashboardOpenSessions->isNotEmpty())
				<div class="dashboard-open-sessions"><strong class="dashboard-open-label"><span></span> ATTENDANCE IS OPEN</strong>
					@foreach ($dashboardOpenSessions as $session)
						@php($mySessionAttendance = $session->attendanceRecords->first())
						<div class="dashboard-open-session"><div><strong>{{ $session->displayName() }}</strong><small>{{ $session->effectiveSportName() }} &middot; {{ $session->venue ?: 'Venue not set' }}</small></div>
							@if (! $mySessionAttendance || $mySessionAttendance->status === 'Pending')
								<form method="POST" action="{{ route('student.attendance.check-in', $session) }}">@csrf<button class="button" type="submit">Check In</button></form>
							@elseif (in_array($mySessionAttendance->status, ['Present', 'Late'], true))
								<span class="badge status-present">Checked in</span>
							@else
								<span class="badge">{{ $mySessionAttendance->status }} recorded</span>
							@endif
						</div>
					@endforeach
				</div>
			@endif
			<a class="text-link" href="{{ route('student.attendance') }}">Full attendance history <span aria-hidden="true">&rarr;</span></a>
		</section>
	</div>

	<div class="student-dashboard-panels">
		<section class="card student-dashboard-events">
			<div class="student-dashboard-heading"><div><span class="student-dashboard-kicker">YOUR PROGRAM</span><h2>Application status</h2></div></div>
			@if ($studentApplication)
				@php($applicationStatus = $studentApplication->status === 'Documents Required' ? 'Incomplete' : $studentApplication->status)
				<div class="student-application-status"><span class="badge">{{ $applicationStatus }}</span><span>{{ $studentApplication->sportCategory?->name ?? $studentApplication->sport ?? 'Sport not assigned' }}</span></div>
				@if ($studentApplication->review_notes)<p class="student-review-notes">{{ $studentApplication->review_notes }}</p>@endif
				<a class="text-link" href="{{ route('student.application') }}">View application <span aria-hidden="true">&rarr;</span></a>
			@else
				<p class="empty-state">No sports application is linked to your account.</p>
			@endif
		</section>
	</div>
</div>
