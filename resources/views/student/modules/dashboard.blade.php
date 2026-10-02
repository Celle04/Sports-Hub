<section class="dashboard-athlete card" aria-label="Student-athlete profile summary">
	<div class="dashboard-avatar">
		@if ($athlete->profile_photo_path)
			<img src="{{ \Illuminate\Support\Facades\Storage::url($athlete->profile_photo_path) }}" alt="{{ $athlete->name }} profile photo">
		@else
			<span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($athlete->name, 0, 2)) }}</span>
		@endif
	</div>
	<div class="dashboard-athlete-name">
		<strong>{{ $athlete->name }}</strong>
		<span>{{ $athlete->sport?->name ?? 'Sport not assigned' }}</span>
	</div>
	<div class="dashboard-athlete-detail"><small>Student ID</small><strong>{{ $athlete->student_id ?: 'Not recorded' }}</strong></div>
	<div class="dashboard-athlete-detail"><small>Grade level</small><strong>{{ $studentApplication?->grade ?: 'Not recorded' }}</strong></div>
	<div class="dashboard-athlete-detail"><small>Athlete status</small><strong><span class="badge {{ $athlete->status === 'Active' ? 'status-present' : '' }}">{{ $athlete->status ?: 'Not recorded' }}</span></strong></div>
	<a class="button button-secondary dashboard-profile-link" href="{{ route('student.profile') }}">View My Profile</a>
</section>

<section class="grid dashboard-stat-grid" aria-label="Sports activity statistics">
	<a class="card dashboard-stat-card" href="{{ route('student.schedule') }}"><span>Upcoming Events</span><strong>{{ $dashboardUpcomingCount }}</strong><small>Scheduled for your sport</small></a>
	<a class="card dashboard-stat-card" href="{{ route('student.attendance') }}"><span>Attendance</span><strong>{{ $dashboardAttendanceCounts['percentage'] }}%</strong><small>Present and late check-ins</small></a>
	<div class="card dashboard-stat-card"><span>Achievements</span><strong class="dashboard-stat-untracked">Not tracked</strong><small>No achievement records in SportsHub yet</small></div>
	<a class="card dashboard-stat-card" href="#portal-notifications"><span>Unread Notifications</span><strong>{{ $dashboardUnreadCount }}</strong><small>Updates from SportsHub</small></a>
</section>

<section class="grid dashboard-main-grid">
	<article class="card dashboard-next-activity">
		<div class="section-heading"><div><span class="dashboard-eyebrow">NEXT ACTIVITY</span><h2>{{ $dashboardNextActivity['title'] ?? 'No upcoming activities' }}</h2></div><svg class="dashboard-section-icon" aria-hidden="true"><use href="#icon-calendar"></use></svg></div>
		@if ($dashboardNextActivity)
			<p class="dashboard-activity-sport">{{ $dashboardNextActivity['sport'] }}</p>
			<div class="dashboard-activity-time"><strong>{{ $dashboardNextActivity['startsAt']->format('F j, Y') }}</strong><span>{{ $dashboardNextActivity['startsAt']->format('g:i A') }}@if ($dashboardNextActivity['endsAt']) - {{ $dashboardNextActivity['endsAt']->format('g:i A') }}@endif</span></div>
			<div class="dashboard-activity-meta"><span><svg aria-hidden="true"><use href="#icon-pin"></use></svg>{{ $dashboardNextActivity['venue'] ?: 'Venue not set' }}</span>@if ($dashboardNextActivity['coach'])<span><svg aria-hidden="true"><use href="#icon-users"></use></svg>Coach {{ $dashboardNextActivity['coach'] }}</span>@endif</div>
			<a class="text-link" href="{{ route('student.schedule') }}">View Schedule <span aria-hidden="true">&rarr;</span></a>
		@else
			<p class="empty-state">No upcoming activities.</p>
			<a class="text-link" href="{{ route('student.schedule') }}">View Schedule <span aria-hidden="true">&rarr;</span></a>
		@endif
	</article>

	<article class="card dashboard-attendance-card">
		<div class="section-heading"><div><span class="dashboard-eyebrow">ATTENDANCE</span><h2>Your participation</h2></div><svg class="dashboard-section-icon" aria-hidden="true"><use href="#icon-activity"></use></svg></div>
		<div class="dashboard-attendance-percent">{{ $dashboardAttendanceCounts['percentage'] }}<span>%</span></div>
		<div class="progress-track dashboard-attendance-progress" role="progressbar" aria-label="Attendance percentage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $dashboardAttendanceCounts['percentage'] }}"><span style="width: {{ $dashboardAttendanceCounts['percentage'] }}%"></span></div>
		<div class="dashboard-attendance-counts"><div><strong>{{ $dashboardAttendanceCounts['present'] }}</strong><span>Present</span></div><div><strong>{{ $dashboardAttendanceCounts['late'] }}</strong><span>Late</span></div><div><strong>{{ $dashboardAttendanceCounts['absent'] }}</strong><span>Absent</span></div><div><strong>{{ $dashboardAttendanceCounts['excused'] }}</strong><span>Excused</span></div></div>
		@if (($dashboardUpcomingAttendance ?? collect())->isNotEmpty())
			<div class="dashboard-open-sessions"><strong class="dashboard-open-label"><span></span> UPCOMING ATTENDANCE</strong>
				@foreach ($dashboardUpcomingAttendance as $session)
					<div class="dashboard-open-session"><div><strong>{{ $session->displayName() }}</strong><small>{{ $session->session_date?->format('M j, Y') }} &middot; {{ $session->venue ?: 'Venue not set' }}</small></div>
						<span class="badge status-pending">Upcoming</span>
					</div>
				@endforeach
			</div>
		@endif
		@if ($dashboardOpenSessions->isNotEmpty())
			<div class="dashboard-open-sessions"><strong class="dashboard-open-label"><span></span> ATTENDANCE IS OPEN</strong>
				@foreach ($dashboardOpenSessions as $session)
                <div class="dashboard-open-session"><div><strong>{{ $session->displayName() }}</strong><small>{{ $session->effectiveSportName() }} &middot; {{ $session->venue ?: 'Venue not set' }}</small></div>
						@php($mySessionAttendance = $session->attendanceRecords->first())
						@if (!$mySessionAttendance || $mySessionAttendance->status === 'Pending')
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
		<a class="text-link dashboard-attendance-link" href="{{ route('student.attendance') }}">View Attendance <span aria-hidden="true">&rarr;</span></a>
	</article>
</section>

<section class="grid dashboard-lower-grid">
	<article class="card dashboard-announcements">
		<div class="section-heading"><div><span class="dashboard-eyebrow">STAY IN THE LOOP</span><h2>Recent Announcements</h2></div><a class="text-link" href="{{ route('student.announcements') }}">View All <span aria-hidden="true">&rarr;</span></a></div>
		@forelse ($announcements->take(3) as $announcement)
			<div class="dashboard-announcement"><span class="dashboard-announcement-icon"><svg aria-hidden="true"><use href="#icon-megaphone"></use></svg></span><div><h3>{{ $announcement->title }}</h3><p>{{ \Illuminate\Support\Str::limit($announcement->body, 150) }}</p><small>{{ $announcement->published_at?->format('F j, Y') ?? 'Published' }}@if ($announcement->sport) &middot; {{ $announcement->sport->name }}@endif</small></div></div>
		@empty
			<p class="empty-state">No announcements have been published for your sport.</p>
		@endforelse
	</article>

	<article class="card dashboard-application">
		<div class="section-heading"><div><span class="dashboard-eyebrow">ATHLETE APPLICATION</span><h2>Application Status</h2></div><svg class="dashboard-section-icon" aria-hidden="true"><use href="#icon-clipboard"></use></svg></div>
		@if ($studentApplication)
			@php($applicationStatus = $studentApplication->status === 'Documents Required' ? 'Incomplete' : $studentApplication->status)
			<span class="badge dashboard-application-status">{{ $applicationStatus }}</span><p>{{ $studentApplication->sportCategory?->name ?? $studentApplication->sport ?? 'Sport not assigned' }}</p>
			@if ($studentApplication->review_notes)<small class="dashboard-review-note">{{ $studentApplication->review_notes }}</small>@endif
			<a class="text-link" href="{{ route('student.application') }}">View application <span aria-hidden="true">&rarr;</span></a>
		@else
			<p class="empty-state">No sports application is linked to your account.</p>
		@endif
	</article>
</section>

<section class="dashboard-quick-actions">
	<div class="section-heading"><div><span class="dashboard-eyebrow">YOUR PORTAL</span><h2>Quick Actions</h2></div></div>
	<nav class="dashboard-action-links" aria-label="Student quick actions">
		<a href="{{ route('student.schedule') }}"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg><span>My Schedule</span><b aria-hidden="true">&rarr;</b></a>
		<a href="{{ route('student.attendance') }}"><svg aria-hidden="true"><use href="#icon-activity"></use></svg><span>Attendance</span><b aria-hidden="true">&rarr;</b></a>
		<a href="{{ route('student.announcements') }}"><svg aria-hidden="true"><use href="#icon-megaphone"></use></svg><span>Announcements</span><b aria-hidden="true">&rarr;</b></a>
		<a href="{{ route('student.profile') }}"><svg aria-hidden="true"><use href="#icon-user"></use></svg><span>My Profile</span><b aria-hidden="true">&rarr;</b></a>
	</nav>
</section>
