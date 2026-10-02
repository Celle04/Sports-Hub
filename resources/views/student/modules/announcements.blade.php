<section class="student-announcements">
	<header class="student-announcements-heading">
		<div><span>FROM YOUR SPORTS COMMUNITY</span><h2>Latest announcements</h2></div>
		<svg aria-hidden="true"><use href="#icon-megaphone"></use></svg>
	</header>

	<div class="student-announcements-list">
		@forelse ($announcements ?? [] as $announcement)
			<article class="card student-announcement-item">
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
						<span>SPORTS HUB UPDATE</span>
						@if ($announcement->sport)<span class="student-announcement-sport">{{ $announcement->sport->name }}</span>@endif
					</div>
					<h3>{{ $announcement->title }}</h3>
					<p>{{ $announcement->body }}</p>
				</div>
			</article>
		@empty
			<div class="student-announcements-empty">
				<span aria-hidden="true"><svg><use href="#icon-megaphone"></use></svg></span>
				<strong>You're all caught up</strong>
				<p>No announcements have been published.</p>
			</div>
		@endforelse
	</div>
</section>
