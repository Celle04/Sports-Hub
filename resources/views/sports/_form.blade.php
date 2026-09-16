<div class="form-group">
    <label for="name">Sport Name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $sport->name ?? '') }}" required>
    @error('name')<small class="form-error">{{ $message }}</small>@enderror
</div>
<div class="form-group" style="margin-top:14px">
    <label for="classification">Classification</label>
    <input class="form-control" id="classification" name="classification" value="{{ old('classification', $sport->classification ?? '') }}" required>
    @error('classification')<small class="form-error">{{ $message }}</small>@enderror
</div>
<div class="form-group" style="margin-top:14px">
    <label for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="4" required>{{ old('description', $sport->description ?? '') }}</textarea>
    @error('description')<small class="form-error">{{ $message }}</small>@enderror
</div>
<div style="display:flex;gap:10px;margin-top:18px">
    <button class="button" type="submit">{{ isset($sport) ? 'Update Sport' : 'Add Sport' }}</button>
    <a class="button" href="{{ route('sports.index') }}">Cancel</a>
</div>