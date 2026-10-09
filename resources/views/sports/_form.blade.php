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
</div>
<div class="form-group">
    <label for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="4" required>{{ old('description', $sport->description ?? '') }}</textarea>
    <small class="form-hint">A short description of the sport and how it is played.</small>
    @error('description')<small class="form-error">{{ $message }}</small>@enderror
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
<div class="medical-form-actions">
    <button class="button" type="submit">{{ isset($sport) ? 'Update Sport' : 'Save Sport' }}</button>
    <a class="button button-muted" href="{{ route('sports.index') }}">Cancel</a>
</div>