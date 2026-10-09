@php($scheduleEvents = collect($events ?? []))

<section class="student-schedule" aria-label="Upcoming schedule">
	<div class="student-schedule-heading">
		<div><span>YOUR SPORTSHUB</span><h2>Upcoming events</h2></div>
		<span class="student-schedule-count">{{ $scheduleEvents->count() }} {{ $scheduleEvents->count() === 1 ? 'event' : 'events' }}</span>
	</div>

	<div class="student-schedule-list">
		@forelse ($scheduleEvents as $event)
			<article class="card student-schedule-item">
				<div class="student-schedule-date">
					<span>{{ $event->starts_at?->format('M') ?? 'TBD' }}</span>
					<strong>{{ $event->starts_at?->format('j') ?? '--' }}</strong>
					<small>{{ $event->starts_at?->format('Y') ?? '' }}</small>
				</div>
				<div class="student-schedule-details">
					<h3>{{ $event->title }}</h3>
					<div class="student-schedule-meta">
						<span><svg aria-hidden="true"><use href="#icon-calendar"></use></svg>{{ $event->starts_at?->format('g:i A') ?? 'Time TBD' }}<span aria-hidden="true">-</span>{{ $event->ends_at?->format('g:i A') ?? 'TBD' }}</span>
						<span><svg aria-hidden="true"><use href="#icon-activity"></use></svg>{{ $event->venue ?: 'Venue to be announced' }}</span>
					</div>
					<p>{{ $event->description ?: 'More event details will be shared soon.' }}</p>
				</div>
			</article>
		@empty
			<div class="student-schedule-empty"><span aria-hidden="true"><svg><use href="#icon-calendar"></use></svg></span><strong>Your schedule is clear</strong><p>No upcoming events are scheduled.</p></div>
		@endforelse
	</div>
</section>
