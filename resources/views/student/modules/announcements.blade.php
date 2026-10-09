<section class="student-announcements">
	<header class="student-announcements-heading">
		<div><span>FROM YOUR SPORTS COMMUNITY</span><h2>Latest announcements</h2></div>
		<svg aria-hidden="true"><use href="#icon-megaphone"></use></svg>
	</header>

	<div class="student-announcements-list">
		@forelse ($announcements ?? [] as $announcement)
			<article id="announcement-{{ $announcement->id }}" @class(['card', 'student-announcement-item', 'is-highlighted' => (int) request('announcement') === $announcement->id])>
				<div class="student-announcement-date">
					@if ($announcement->published_at)
						<time datetime="{{ $announcement->published_at->toDateString() }}">
							<span>{{ $announcement->published_at->format('M') }}</span>
							<strong>{{ $announcement->published_at->format('j') }}</strong>
							<small>{{ $announcement->published_at->format('Y') }}</small>
						</time>
					@else
						<span class="student-announcement-upcoming">Upcoming</span>
					@endif
				</div>
				<div class="student-announcement-copy">
					<div class="student-announcement-tags">
						<span>SPORTSHUB UPDATE</span>
						@if ($announcement->sport)<span class="student-announcement-sport">{{ $announcement->sport->name }}</span>@endif
					</div>
					<h3>{{ $announcement->title }}</h3>
					<p>{{ $announcement->body }}</p>
					<small class="meta relative-time" data-posted-at="{{ \App\Support\RelativeTime::machine($announcement->postedAt()) }}" data-posted-prefix="Posted ">Posted {{ $announcement->postedForHumans() }}</small>
				</div>
			</article>
		@empty
			<div class="student-announcements-empty">
				<span aria-hidden="true"><svg><use href="#icon-megaphone"></use></svg></span>
				<strong>No announcements yet.</strong>
				<p>New updates from the Sports Office will appear here.</p>
			</div>
		@endforelse
	</div>
</section>
