@php($calendarMonth = $calendarMonth ?? now()->startOfMonth())

<section class="student-calendar">
	<div class="card calendar-card student-calendar-card">
		<div class="calendar-toolbar student-calendar-toolbar">
			<a class="calendar-arrow" aria-label="Previous month" href="{{ route('student.calendar', ['month' => $calendarMonth->copy()->subMonth()->format('Y-m')]) }}">&larr;</a>
			<div class="student-calendar-title">
				<span>MONTHLY OVERVIEW</span>
				<h2>{{ $calendarMonth->format('F Y') }}</h2>
			</div>
			<div class="student-calendar-controls">
				<a class="student-calendar-today" href="{{ route('student.calendar') }}">Today</a>
				<a class="calendar-arrow" aria-label="Next month" href="{{ route('student.calendar', ['month' => $calendarMonth->copy()->addMonth()->format('Y-m')]) }}">&rarr;</a>
			</div>
		</div>

		<div class="student-calendar-scroll">
			<div class="calendar-grid calendar-weekdays student-calendar-weekdays">
				<span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
			</div>

			<div class="calendar-grid student-calendar-grid">
				@foreach ($calendarDays ?? [] as $day)
					@php($dateString = $day->toDateString())
					@php($dayEvents = collect($calendarEvents->get($dateString, [])))
					<div class="calendar-day student-calendar-day {{ $day->month !== $calendarMonth->month ? 'outside-month' : '' }} {{ $day->isToday() ? 'today' : '' }} {{ $dayEvents->isNotEmpty() ? 'has-event' : '' }}">
						<div class="day-number">{{ $day->day }}</div>
						@foreach ($dayEvents->take(2) as $event)
							<span class="calendar-event" title="{{ $event->title }}">{{ $event->title }}</span>
						@endforeach
						@if ($dayEvents->count() > 2)
							<span class="calendar-more">+{{ $dayEvents->count() - 2 }} more</span>
						@endif
					</div>
				@endforeach
			</div>
		</div>

		<div class="student-calendar-legend"><span><i></i> Today</span><span><i></i> Scheduled activity</span></div>
	</div>
</section>
