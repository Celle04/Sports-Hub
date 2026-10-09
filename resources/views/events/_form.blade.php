<div class="grid form-grid">
    <div class="form-group"><label for="title">Event name</label><input class="form-control" id="title" name="title" value="{{ old('title', $event->title ?? '') }}" required></div>
    <div class="form-group"><label for="sport_id">Sport</label><select class="form-control" id="sport_id" name="sport_id" required><option value="">Select sport</option>@foreach($sports as $sport)<option value="{{ $sport->id }}" @selected((string) old('sport_id', $event->sport_id ?? '') === (string) $sport->id)>{{ $sport->name }}</option>@endforeach</select></div>
    <div class="form-group"><label for="event_type">Event type</label><select class="form-control" id="event_type" name="event_type" required>@foreach($eventTypes as $eventType)<option value="{{ $eventType }}" @selected(old('event_type', $event->event_type ?? 'Other') === $eventType)>{{ $eventType }}</option>@endforeach</select></div>
    <div class="form-group"><label for="venue">Venue</label><input class="form-control" id="venue" name="venue" value="{{ old('venue', $event->venue ?? '') }}" required></div>
</div>
<div class="grid form-grid">
    <div class="form-group"><label for="starts_at">Date and start time</label><input class="form-control" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', isset($event) ? $event->starts_at->format('Y-m-d\\TH:i') : '') }}" required></div>
    <div class="form-group"><label for="ends_at">Date and end time</label><input class="form-control" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', isset($event) ? $event->ends_at->format('Y-m-d\\TH:i') : '') }}" required></div>
    <div class="form-group"><label for="coach_id">Coach</label><select class="form-control" id="coach_id" name="coach_id"><option value="">No coach assigned</option>@foreach($coaches as $coach)<option value="{{ $coach->id }}" @selected((string) old('coach_id', $event->coach_id ?? '') === (string) $coach->id)>{{ $coach->name }}</option>@endforeach</select></div>
    <div class="form-group"><label for="team_name">Team</label><input class="form-control" id="team_name" name="team_name" value="{{ old('team_name', $event->team_name ?? '') }}" placeholder="Optional team name"></div>
</div>
<div class="grid form-grid">
    <div class="form-group"><label for="max_participants">Maximum participants</label><input class="form-control" id="max_participants" name="max_participants" type="number" min="1" value="{{ old('max_participants', $event->max_participants ?? '') }}"></div>
    <div class="form-group"><label for="status">Status</label><select class="form-control" id="status" name="status" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $event->status ?? 'Scheduled') === $status)>{{ $status }}</option>@endforeach</select></div>
</div>
<div class="form-group"><label for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $event->description ?? '') }}</textarea></div>
<div class="form-group"><label for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $event->notes ?? '') }}</textarea></div>
<div class="event-form-errors">
    @foreach(['title','sport_id','event_type','venue','starts_at','ends_at','coach_id','team_name','max_participants','description','notes','status'] as $field) @error($field)<small class="form-error">{{ $message }}</small>@enderror @endforeach
</div>
<div class="medical-form-actions"><button class="button" type="submit">{{ isset($event) ? 'Update Event' : 'Save Event' }}</button><a class="button button-muted" href="{{ route('events.index') }}">Cancel</a></div>