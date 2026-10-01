@php
    $calendarSummary = $summary ?? [
        'month' => 0,
        'week' => 0,
        'today' => 0,
    ];

    $currentMonth = $calendarMonth ?? now();
@endphp


{{-- =========================================
     CALENDAR SUMMARY
========================================= --}}
<section class="card calendar-summary">
    <div class="grid grid-3">

        {{-- This Month --}}
        <div class="card stat">
            <small>This Month</small>
            <strong>{{ $calendarSummary['month'] }}</strong>
            <span>Events</span>
        </div>

        {{-- This Week --}}
        <div class="card stat">
            <small>This Week</small>
            <strong>{{ $calendarSummary['week'] }}</strong>
            <span>Events</span>
        </div>

        {{-- Today --}}
        <div class="card stat">
            <small>Today</small>
            <strong>{{ $calendarSummary['today'] }}</strong>
            <span>Events</span>
        </div>

    </div>
</section>


{{-- =========================================
     CALENDAR
========================================= --}}
<section class="card calendar-card">

    {{-- Calendar Header --}}
    <div class="module-header">

        <h2>
            {{ $currentMonth->format('F Y') }}
        </h2>

        <div class="calendar-navigation">

            {{-- Previous Month --}}
            <a
                class="button button-secondary"
                href="{{ request()->fullUrlWithQuery([
                    'month' => $currentMonth->copy()->subMonth()->format('Y-m')
                ]) }}"
            >
                Previous
            </a>

            {{-- Current Month --}}
            <a
                class="button button-secondary"
                href="{{ route('admin.calendar') }}"
            >
                Today
            </a>

            {{-- Next Month --}}
            <a
                class="button button-secondary"
                href="{{ request()->fullUrlWithQuery([
                    'month' => $currentMonth->copy()->addMonth()->format('Y-m')
                ]) }}"
            >
                Next
            </a>

        </div>
    </div>


    {{-- =========================================
         FILTERS
    ========================================== --}}
    <form
        method="GET"
        action="{{ route('admin.calendar') }}"
        class="filters"
    >

        {{-- Sport Filter --}}
        <select
            class="form-control"
            name="sport_id"
        >
            <option value="">All Sports</option>

            @foreach ($sports ?? [] as $sport)
                <option
                    value="{{ $sport->id }}"
                    @selected((string) request('sport_id') === (string) $sport->id)
                >
                    {{ $sport->name }}
                </option>
            @endforeach
        </select>


        {{-- Venue Filter --}}
        <select
            class="form-control"
            name="venue"
        >
            <option value="">All Venues</option>

            @foreach ($venues ?? [] as $venue)
                <option
                    value="{{ $venue }}"
                    @selected(request('venue') === $venue)
                >
                    {{ $venue }}
                </option>
            @endforeach
        </select>


        {{-- Apply Filters --}}
        <button
            class="button"
            type="submit"
        >
            Apply Filters
        </button>


        {{-- Reset Filters --}}
        <a
            class="button button-secondary"
            href="{{ route('admin.calendar') }}"
        >
            Reset
        </a>

    </form>


    {{-- =========================================
         CALENDAR WEEKDAYS
    ========================================== --}}
    <div class="calendar-grid calendar-weekdays">
        <span>Sun</span>
        <span>Mon</span>
        <span>Tue</span>
        <span>Wed</span>
        <span>Thu</span>
        <span>Fri</span>
        <span>Sat</span>
    </div>


    {{-- =========================================
         CALENDAR DAYS
    ========================================== --}}
    <div class="calendar-grid">

        @foreach ($calendarDays ?? [] as $day)

            @php
                $dateString = $day->toDateString();
            @endphp

            <div class="calendar-day">

                {{-- Day Number --}}
                <div class="day-number">
                    {{ $day->day }}
                </div>


                {{-- Events --}}
                @foreach (
                    collect($calendarEvents->get($dateString, []))->take(2)
                    as $event
                )

                    <a
                        class="calendar-event"
                        href="{{ route('events.show', $event) }}"
                    >
                        {{ $event->title }}
                    </a>

                @endforeach

            </div>

        @endforeach

    </div>

</section>