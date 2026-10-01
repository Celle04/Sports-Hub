<div class="grid grid-3">
	<a class="card stat" href="{{ route('student.schedule') }}">
		<strong>My Schedule</strong>
		<small>View upcoming events</small>
	</a>
	<a class="card stat" href="{{ route('student.attendance') }}">
		<strong>Attendance</strong>
		<small>View your participation record</small>
	</a>
	<a class="card stat" href="{{ route('student.coach') }}">
		<strong>Coach Info</strong>
		<small>View your assigned coach</small>
	</a>
</div>

<section class="card soft-card" style="margin-top:16px">
	<h2>Recent announcements</h2>
	@forelse ($announcements->take(3) as $announcement)
		<article class="list-card">
			<h3>{{ $announcement->title }}</h3>
			<div class="meta">{{ $announcement->published_at?->format('M j, Y') ?? 'Upcoming' }}</div>
			<p>{{ $announcement->body }}</p>
		</article>
	@empty
		<p>No current announcements.</p>
	@endforelse
</section>

<div class="grid grid-2" style="margin-top:16px">
	<section class="card">
		<h2>Upcoming activities</h2>
		@forelse ($upcomingStudentEvents ?? [] as $event)
			<article class="event-preview">
				<strong>{{ $event->title }}</strong>
				<p>{{ $event->starts_at?->format('M j, Y g:i A') }} &middot; {{ $event->venue ?: 'Venue not set' }}</p>
				<p class="meta">{{ $event->sport?->name ?? 'All sports' }} &middot; Coach {{ $event->coach?->name ?? 'Not assigned' }}</p>
			</article>
		@empty
			<p class="empty-state">No upcoming events.</p>
		@endforelse
	</section>
	<section class="card">
		<h2>Application status</h2>
		@if ($studentApplication)
			<p><span class="badge">{{ $studentApplication->status }}</span></p>
			<p class="meta">{{ $studentApplication->sportCategory?->name ?? $studentApplication->sport ?? 'Sport not assigned' }}</p>
			@if ($studentApplication->review_notes)<p>{{ $studentApplication->review_notes }}</p>@endif
			<a class="text-link" href="{{ route('student.application') }}">View application</a>
		@else
			<p class="empty-state">No sports application is linked to your account.</p>
		@endif
	</section>
</div>
