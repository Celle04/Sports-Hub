<div class="medical-card-head">
    <div>
        <span class="admin-dashboard-kicker">COACH RECORD</span>
        <h2>Coach Details</h2>
        <p class="meta">Contact details, specialty and the sport this coach manages.</p>
    </div>
</div>

<div class="form-grid">
    <div class="form-group">
        <label for="name">Full Name</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" type="text" value="{{ old('name', $coach->name ?? '') }}" placeholder="e.g. Coach Linda Santos" required>
        @error('name')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $coach->email ?? '') }}" placeholder="coach@snhhs.edu.ph" required>
        @error('email')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="phone">Phone</label>
        <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="text" value="{{ old('phone', $coach->phone ?? '') }}" placeholder="+63 913 456 7890">
        <small class="form-hint">Optional contact number for the coach.</small>
        @error('phone')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="specialty">Specialty</label>
        <input class="form-control @error('specialty') is-invalid @enderror" id="specialty" name="specialty" type="text" value="{{ old('specialty', $coach->specialty ?? '') }}" placeholder="e.g. Court tactics, conditioning" required>
        @error('specialty')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="sport_id">Assigned Sport</label>
        <select class="form-control @error('sport_id') is-invalid @enderror" id="sport_id" name="sport_id" required>
            <option value="">Select sport</option>
            @foreach ($sports as $sport)
                <option value="{{ $sport->id }}" @selected((string) old('sport_id', $coach->sport_id ?? '') === (string) $sport->id)>{{ $sport->name }}</option>
            @endforeach
        </select>
        @error('sport_id')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="coach_type">Coach Type</label>
        <select class="form-control @error('coach_type') is-invalid @enderror" id="coach_type" name="coach_type">
            @foreach ($coachTypes as $coachType)
                <option value="{{ $coachType }}" @selected(old('coach_type', $coach->coach_type ?? 'Head Coach') === $coachType)>{{ $coachType }}</option>
            @endforeach
        </select>
        <small class="form-hint">Head coaches lead the team; assistant coaches support them.</small>
        @error('coach_type')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $coach->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <small class="form-hint">Inactive coaches are hidden from the student portal but kept on record.</small>
        @error('status')<small class="form-error">{{ $message }}</small>@enderror
    </div>
</div>

<div class="medical-form-actions">
    <button class="button" type="submit">{{ isset($coach) ? 'Update Coach' : 'Save Coach' }}</button>
    <a class="button button-muted" href="{{ route('coaches.index') }}">Cancel</a>
</div>