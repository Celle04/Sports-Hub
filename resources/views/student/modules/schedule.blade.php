@forelse ($events ?? [] as $event)
	<article class="card list-card">
		<h3>{{ $event->title }}</h3>
		<div class="meta">{{ $event->starts_at->format('M j, Y g:i A') }} &middot; {{ $event->ends_at->format('g:i A') }} &middot; {{ $event->venue }}</div>
		<p>{{ $event->description }}</p>
	</article>
@empty
	<div class="card"><p>No upcoming events are scheduled.</p></div>
@endforelse
