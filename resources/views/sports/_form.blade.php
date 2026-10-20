<div class="grid form-grid">
    <div class="form-group">
        <label for="name">Sport Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $sport->name ?? '') }}" required>
        @error('name')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="classification">Classification</label>
        <select class="form-control" id="classification" name="classification" required>
            <option value="">Select classification</option>
            @foreach ($classifications as $classification)
                <option value="{{ $classification }}" @selected(old('classification', $sport->classification ?? '') === $classification)>{{ $classification }}</option>
            @endforeach
        </select>
        @error('classification')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="gender">Gender</label>
        <select class="form-control" id="gender" name="gender" required>
            <option value="">Select gender</option>
            @foreach ($genders as $gender)
                <option value="{{ $gender }}" @selected(old('gender', $sport->gender ?? 'Boys') === $gender)>{{ $gender }}</option>
            @endforeach
        </select>
        <small class="form-hint">Denotes whether the program is open to boys or girls.</small>
        @error('gender')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select class="form-control" id="status" name="status">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $sport->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <small class="form-hint">Inactive sports are hidden from student sign-ups but kept on record.</small>
        @error('status')<small class="form-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="coach_id">Coach Assignment</label>
        <select class="form-control @error('coach_id') is-invalid @enderror" id="coach_id" name="coach_id">
            <option value="">Unassigned</option>
            @foreach ($coaches ?? [] as $coach)
                <option value="{{ $coach->id }}" @selected((string) old('coach_id', $assignedCoachId ?? '') === (string) $coach->id)>
                    {{ $coach->name }} &mdash; {{ $coach->sport?->name ?? ($coach->specialty ?: 'No sport assigned') }}@if ($coach->status !== 'Active') (Inactive)@endif
                </option>
            @endforeach
        </select>
        <small class="form-hint">Assign a coach to this program. A coach can only hold one sport, so choosing a coach already assigned elsewhere moves them here.</small>
        @error('coach_id')<small class="form-error">{{ $message }}</small>@enderror
    </div>
</div>
<div class="form-group">
    <label for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="4" required>{{ old('description', $sport->description ?? '') }}</textarea>
    <small class="form-hint">A short description of the sport and how it is played.</small>
    @error('description')<small class="form-error">{{ $message }}</small>@enderror
</div>
<div class="form-group">
    <label for="logo">Sport Logo</label>
    @if (isset($sport) && $sport->logo_path)
        <div class="image-preview" style="margin-bottom: 10px;">
            <img src="{{ Storage::disk('public')->url($sport->logo_path) }}" alt="{{ $sport->name }} logo" style="max-height: 120px; max-width: 100%; object-fit: contain;">
            <div style="margin-top: 6px;">
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="remove_logo" value="1" {{ old('remove_logo') ? 'checked' : '' }}>
                    <span>Remove current logo</span>
                </label>
            </div>
        </div>
    @endif
    <input class="form-control" id="logo" name="logo" type="file" accept="image/jpeg,image/jpg,image/png,image/webp">
    <small class="form-hint">JPG, JPEG, PNG, or WebP. Max 2 MB. Original aspect ratio preserved.</small>
    @error('logo')<small class="form-error">{{ $message }}</small>@enderror
</div>
<div class="medical-form-actions">
    <button class="button" type="submit">{{ isset($sport) ? 'Update Sport' : 'Save Sport' }}</button>
    <a class="button button-muted" href="{{ route('sports.index') }}">Cancel</a>
</div>