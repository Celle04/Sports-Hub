@php
    /*
     * The attendance fields are rendered in two situations:
     *  - on the Create Event screen they are nested in the event form
     *    ($attendancePrefix = 'attendance'), so one submit saves the event
     *    together with its first attendance session;
     *  - on the Edit Event screen they belong to their own form
     *    ($attendancePrefix = null, $attendanceFormId set), so a new session
     *    can be attached to an event that already exists.
     */
    $field = fn (string $name) => ($attendancePrefix ? $attendancePrefix.'['.$name.']' : $name);
    $old = fn (string $name, $fallback = null) => old($attendancePrefix ? $attendancePrefix.'.'.$name : $name, $fallback);
    $linked = fn (string $name) => $attendanceFormId ? ' form="'.$attendanceFormId.'"' : '';
    $eventDate = isset($event) && $event->starts_at ? $event->starts_at->format('Y-m-d') : today()->toDateString();
    $eventStart = isset($event) && $event->starts_at ? $event->starts_at->format('H:i') : '';
    $eventEnd = isset($event) && $event->ends_at ? $event->ends_at->format('H:i') : '';
@endphp

<div class="event-attendance-grid">
    <div class="form-group">
        <label for="attendance-title">Attendance title</label>
        <input class="form-control" id="attendance-title" name="{{ $field('title') }}" value="{{ $old('title', isset($event) ? $event->title.' Attendance' : '') }}" placeholder="Training attendance"{{ $attendancePrefix ? '' : ' required' }}{{!! $linked('title') !!}>
    </div>
    <div class="form-group">
        <label for="attendance-date">Attendance date</label>
        <input class="form-control" id="attendance-date" type="date" name="{{ $field('session_date') }}" value="{{ $old('session_date', $eventDate) }}"{{ $attendancePrefix ? '' : ' required' }}{{!! $linked('session_date') !!}>
    </div>
    <div class="form-group">
        <label for="attendance-start">Start time</label>
        <input class="form-control" id="attendance-start" type="time" name="{{ $field('start_time') }}" value="{{ $old('start_time', $eventStart) }}"{!! $linked('start_time') !!}>
    </div>
    <div class="form-group">
        <label for="attendance-end">End time</label>
        <input class="form-control" id="attendance-end" type="time" name="{{ $field('end_time') }}" value="{{ $old('end_time', $eventEnd) }}"{!! $linked('end_time') !!}>
    </div>
    <div class="form-group">
        <label for="attendance-sport">Sport</label>
        <select class="form-control" id="attendance-sport" name="{{ $field('sport_id') }}"{!! $linked('sport_id') !!}>
            <option value="">Same as the event</option>
            @foreach ($sports as $sport)
                <option value="{{ $sport->id }}" @selected((string) $old('sport_id', $event->sport_id ?? '') === (string) $sport->id)>{{ $sport->name }}</option>
            @endforeach
        </select>
        <small class="form-hint">Leave as the event sport so the roster follows the athletes assigned to it.</small>
    </div>
    <div class="form-group">
        <label for="attendance-venue">Venue</label>
        <input class="form-control" id="attendance-venue" name="{{ $field('venue') }}" value="{{ $old('venue', $event->venue ?? '') }}" placeholder="SNNHS Gymnasium"{!! $linked('venue') !!}>
    </div>
    <div class="form-group">
        <label for="attendance-grace">Late grace (minutes)</label>
        <input class="form-control" id="attendance-grace" type="number" name="{{ $field('late_grace_minutes') }}" min="0" max="120" value="{{ $old('late_grace_minutes', config('attendance.late_grace_minutes')) }}"{!! $linked('late_grace_minutes') !!}>
    </div>
    <div class="form-group">
        <label for="attendance-status">Session status</label>
        <select class="form-control" id="attendance-status" name="{{ $field('status') }}"{!! $linked('status') !!}>
            <option value="Open" @selected($old('status', 'Open') === 'Open')>Open</option>
            <option value="Closed" @selected($old('status', 'Open') === 'Closed')>Closed</option>
            <option value="Cancelled" @selected($old('status', 'Open') === 'Cancelled')>Cancelled</option>
        </select>
    </div>
    <div class="form-group event-attendance-full">
        <label for="attendance-description">Attendance notes</label>
        <textarea class="form-control" id="attendance-description" name="{{ $field('description') }}" rows="2" placeholder="Optional notes about this attendance session"{!! $linked('description') !!}>{{ $old('description') }}</textarea>
    </div>
</div>

@foreach (['title', 'session_date', 'start_time', 'end_time', 'sport_id', 'venue', 'status', 'description', 'late_grace_minutes'] as $attendanceField)
    @error($attendancePrefix ? $attendancePrefix.'.'.$attendanceField : $attendanceField)
        <small class="form-error">{{ $message }}</small>
    @enderror
@endforeach
