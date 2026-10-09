@php
    $calendarSummary = $summary ?? [
        'month' => 0,
        'week' => 0,
        'today' => 0,
    ];

    $currentMonth = $calendarMonth ?? now();
    $viewingCurrentMonth = $currentMonth->isSameMonth(now());
    $monthLabel = $viewingCurrentMonth ? 'This Month' : $currentMonth->format('M Y');
@endphp

<section class="admin-calendar">

    {{-- =========================================
         SUMMARY STRIP
    ========================================== --}}
    <div class="card admin-calendar-stats">
        <div class="admin-calendar-stat">
            <small>{{ $monthLabel }}</small>
            <strong>{{ $calendarSummary['month'] }}</strong>
            <span>{{ \Illuminate\Support\Str::plural('Event', $calendarSummary['month']) }}</span>
        </div>
        <div class="admin-calendar-stat">
            <small>This Week</small>
            <strong>{{ $calendarSummary['week'] }}</strong>
            <span>{{ \Illuminate\Support\Str::plural('Event', $calendarSummary['week']) }}</span>
        </div>
        <div class="admin-calendar-stat">
            <small>Today</small>
            <strong>{{ $calendarSummary['today'] }}</strong>
            <span>{{ \Illuminate\Support\Str::plural('Event', $calendarSummary['today']) }}</span>
        </div>
    </div>

    {{-- =========================================
         CALENDAR
    ========================================== --}}
    <section class="card calendar-card admin-calendar-card">

        <div class="calendar-toolbar admin-calendar-toolbar">
            <div class="admin-calendar-nav">
                <a class="calendar-arrow" aria-label="Previous month" href="{{ request()->fullUrlWithQuery(['month' => $currentMonth->copy()->subMonth()->format('Y-m')]) }}"><svg aria-hidden="true"><use href="#icon-chevron-left"></use></svg></a>

                <div class="admin-calendar-title">
                    <span>MONTHLY OVERVIEW</span>
                    <h2>{{ $currentMonth->format('F Y') }}</h2>
                </div>

                <a class="calendar-arrow" aria-label="Next month" href="{{ request()->fullUrlWithQuery(['month' => $currentMonth->copy()->addMonth()->format('Y-m')]) }}"><svg aria-hidden="true"><use href="#icon-chevron-right"></use></svg></a>
            </div>

            <div class="admin-calendar-actions">
                <a class="admin-calendar-today" href="{{ route('admin.calendar') }}">Today</a>
                <a class="button" href="{{ route('events.create') }}">New Event</a>
            </div>

            <form method="GET" action="{{ route('admin.calendar') }}" class="admin-calendar-filters" data-auto-submit>
                {{-- Keep the month the admin is looking at when filtering. --}}
                <input type="hidden" name="month" value="{{ $currentMonth->format('Y-m') }}">

                <label class="visually-hidden" for="calendar-filter-sport">Sport</label>
                <select class="form-control" id="calendar-filter-sport" name="sport_id">
                    <option value="">All sports</option>
                    @foreach ($sports ?? [] as $sport)
                        <option value="{{ $sport->id }}" @selected((string) request('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>
                    @endforeach
                </select>

                <label class="visually-hidden" for="calendar-filter-type">Event type</label>
                <select class="form-control" id="calendar-filter-type" name="event_type">
                    <option value="">All types</option>
                    @foreach ($eventTypes ?? [] as $eventType)
                        <option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>{{ $eventType }}</option>
                    @endforeach
                </select>

                <label class="visually-hidden" for="calendar-filter-venue">Venue</label>
                <select class="form-control" id="calendar-filter-venue" name="venue">
                    <option value="">All venues</option>
                    @foreach ($venues ?? [] as $venue)
                        <option value="{{ $venue }}" @selected(request('venue') === $venue)>{{ $venue }}</option>
                    @endforeach
                </select>

                <label class="visually-hidden" for="calendar-filter-status">Status</label>
                <select class="form-control" id="calendar-filter-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses ?? [] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>

                {{-- Present so the filters still work without JavaScript. --}}
                <button class="button admin-calendar-apply" type="submit">Apply</button>

                @if (request()->filled('sport_id') || request()->filled('event_type') || request()->filled('venue') || request()->filled('status'))
                    <a class="button button-secondary" href="{{ route('admin.calendar', ['month' => $currentMonth->format('Y-m')]) }}">Clear</a>
                @endif
            </form>
        </div>

        <div class="admin-calendar-scroll">
            <div class="calendar-grid calendar-weekdays admin-calendar-weekdays">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>

            <div class="calendar-grid admin-calendar-grid">
            @foreach ($calendarDays ?? [] as $day)
                @php
                    $dateString = $day->toDateString();
                    $dayEvents = $calendarEvents->get($dateString, collect());
                    $visibleEvents = $dayEvents->take(3);
                    $hiddenCount = $dayEvents->count() - $visibleEvents->count();
                    $isOutsideMonth = $day->month !== $currentMonth->month || $day->year !== $currentMonth->year;
                    $dayClasses = array_filter([
                        'calendar-day',
                        'admin-calendar-day',
                        $isOutsideMonth ? 'outside-month' : null,
                        $day->isToday() ? 'today' : null,
                        $dayEvents->isNotEmpty() ? 'has-event' : null,
                    ]);
                @endphp

                <div class="{{ implode(' ', $dayClasses) }}">
                    <div class="day-number">{{ $day->day }}</div>

                    @foreach ($visibleEvents as $event)
                        <a
                            class="calendar-event admin-calendar-event event-status-{{ strtolower($event->status) }}"
                            href="{{ route('events.show', $event) }}"
                            title="{{ $event->title }} &middot; {{ $event->starts_at->format('g:i A') }} &middot; {{ $event->status }}"
                        >
                            <span class="admin-calendar-event-time">{{ $event->starts_at->format('g:i A') }}</span>
                            <span class="admin-calendar-event-title">{{ $event->title }}</span>
                        </a>
                    @endforeach

                    @if ($hiddenCount > 0)
                        <span class="calendar-more">+{{ $hiddenCount }} more</span>
                    @endif
                </div>
            @endforeach
            </div>
        </div>

        <div class="admin-calendar-legend">
            <span><i class="legend-scheduled"></i> Scheduled</span>
            <span><i class="legend-ongoing"></i> Ongoing</span>
            <span><i class="legend-completed"></i> Completed</span>
            <span><i class="legend-cancelled"></i> Cancelled</span>
            <span><i class="legend-postponed"></i> Postponed</span>
        </div>
    </section>

    {{-- =========================================
         NEXT UP
    ========================================== --}}
    <aside class="card admin-calendar-upcoming">
        <header class="admin-calendar-upcoming-heading">
            <span>NEXT UP</span>
            <a class="text-link" href="{{ route('events.index') }}">All events</a>
        </header>

        @forelse ($upcomingEvents ?? [] as $event)
            <a class="admin-calendar-upcoming-row" href="{{ route('events.show', $event) }}">
                <div class="admin-calendar-upcoming-date">
                    <strong>{{ $event->starts_at->format('j') }}</strong>
                    <span>{{ $event->starts_at->format('M') }}</span>
                </div>
                <div class="admin-calendar-upcoming-copy">
                    <strong>{{ $event->title }}</strong>
                    <small>{{ $event->starts_at->format('g:i A') }}@if ($event->venue) &middot; {{ $event->venue }}@endif</small>
                </div>
            </a>
        @empty
            <p class="empty-state">No upcoming events scheduled.</p>
        @endforelse
    </aside>
</section>
