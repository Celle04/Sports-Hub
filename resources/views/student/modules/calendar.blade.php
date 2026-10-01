@php($calendarMonth = $calendarMonth ?? now()->startOfMonth())

<div class="card calendar-card">
	<div class="calendar-toolbar">
		<a class="calendar-arrow" href="{{ route('student.calendar', ['month' => $calendarMonth->copy()->subMonth()->format('Y-m')]) }}">&larr;</a>
		<h2>{{ $calendarMonth->format('F Y') }}</h2>
		<a class="calendar-arrow" href="{{ route('student.calendar', ['month' => $calendarMonth->copy()->addMonth()->format('Y-m')]) }}">&rarr;</a>
	</div>

	<div class="calendar-grid calendar-weekdays">
		<span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
	</div>

	<div class="calendar-grid">
		@foreach ($calendarDays ?? [] as $day)
			@php($dateString = $day->toDateString())
			<div class="calendar-day {{ $day->month !== $calendarMonth->month ? 'outside-month' : '' }} {{ $day->isToday() ? 'today' : '' }}">
				<div class="day-number">{{ $day->day }}</div>
				@foreach (collect($calendarEvents->get($dateString, []))->take(2) as $event)
					<span class="calendar-event">{{ $event->title }}</span>
				@endforeach
			</div>
		@endforeach
	</div>
</div>
