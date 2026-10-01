@forelse ($announcements ?? [] as $announcement)
	<article class="card list-card">
		<h3>{{ $announcement->title }}</h3>
		<div class="meta">
			{{ $announcement->published_at?->format('M j, Y') ?? 'Upcoming' }}
			@if ($announcement->sport)
				&middot; {{ $announcement->sport->name }}
			@endif
		</div>
		<p>{{ $announcement->body }}</p>
	</article>
@empty
	<div class="card"><p>No announcements have been published.</p></div>
@endforelse
