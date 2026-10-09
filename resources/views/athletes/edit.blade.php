@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Edit Athlete</h1><p class="page-subtitle">Update athlete account and sport assignment</p></div><div class="event-header-actions"><a class="button button-secondary" href="{{ route('athletes.show', $athlete) }}">Back to Profile</a><a class="button button-secondary" href="{{ route('athletes.index') }}">Back to Athletes</a></div></div>
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
<form class="card athlete-edit-form" method="POST" action="{{ route('athletes.update', $athlete) }}" enctype="multipart/form-data">
	@csrf
	@method('PUT')

	<fieldset class="athlete-photo-fieldset">
		<legend>Profile photo</legend>
		<div class="athlete-photo-row">
			<x-avatar :user="$athlete" size="lg" id="profile-photo-preview" class="athlete-avatar" />

			<div class="athlete-photo-fields">
				<div class="form-group">
					<label for="profile_photo">Upload a new photo</label>
					<input
						class="form-control @error('profile_photo') is-invalid @enderror"
						id="profile_photo"
						name="profile_photo"
						type="file"
						accept="image/jpeg,image/png,image/webp"
						data-photo-input
					>
					<small>JPG, JPEG, PNG or WEBP &middot; maximum 2 MB.</small>
					@error('profile_photo')<small class="form-error">{{ $message }}</small>@enderror
				</div>

				@if ($athlete->profile_photo_path)
					<label class="athlete-photo-remove" for="remove_photo">
						<input id="remove_photo" name="remove_photo" type="checkbox" value="1" @checked(old('remove_photo'))>
						<span>Remove the current photo</span>
					</label>
				@endif
			</div>
		</div>
	</fieldset>

	<div class="form-grid">
		<div class="form-group">
			<label for="name">Full name</label>
			<input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $athlete->name) }}" required>
			@error('name')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="student_id">Student ID</label>
			<input class="form-control @error('student_id') is-invalid @enderror" id="student_id" name="student_id" value="{{ old('student_id', $athlete->student_id) }}">
			@error('student_id')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="username">Username</label>
			<input class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $athlete->username) }}">
			@error('username')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="email">Email</label>
			<input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $athlete->email) }}" required>
			@error('email')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="sport_id">Sport</label>
			<select class="form-control @error('sport_id') is-invalid @enderror" id="sport_id" name="sport_id">
				<option value="">Unassigned</option>
				@foreach ($sports as $sport)<option value="{{ $sport->id }}" @selected((string) old('sport_id', $athlete->sport_id) === (string) $sport->id)>{{ $sport->name }}{{ $sport->status === 'Inactive' ? ' (Inactive)' : '' }}</option>@endforeach
			</select>
			@error('sport_id')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="status">Status</label>
			<select class="form-control @error('status') is-invalid @enderror" id="status" name="status">
				@foreach ($statuses as $status)<option value="{{ $status }}" @selected(old('status', $athlete->status) === $status)>{{ $status }}</option>@endforeach
			</select>
			@error('status')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="grade_level">Grade level</label>
			<input class="form-control @error('grade_level') is-invalid @enderror" id="grade_level" name="grade_level" value="{{ old('grade_level', $athlete->grade_level) }}" placeholder="{{ $athlete->gradeLevel() ?: 'e.g. Grade 10' }}">
			@if (! $athlete->grade_level && $athlete->gradeLevel())<small class="form-hint">Showing the grade from the athlete's application: {{ $athlete->gradeLevel() }}.</small>@else<small class="form-hint">Leave blank to fall back to the grade on the application.</small>@endif
			@error('grade_level')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="emergency_contact_name">Emergency contact name</label>
			<input class="form-control @error('emergency_contact_name') is-invalid @enderror" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $athlete->emergency_contact_name) }}" autocomplete="off">
			@error('emergency_contact_name')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="emergency_contact_relationship">Emergency contact relationship</label>
			<input class="form-control @error('emergency_contact_relationship') is-invalid @enderror" id="emergency_contact_relationship" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $athlete->emergency_contact_relationship) }}" placeholder="e.g. Mother" autocomplete="off">
			@error('emergency_contact_relationship')<small class="form-error">{{ $message }}</small>@enderror
		</div>
		<div class="form-group">
			<label for="emergency_contact_phone">Emergency contact number</label>
			<input class="form-control @error('emergency_contact_phone') is-invalid @enderror" id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone', $athlete->emergency_contact_phone) }}" autocomplete="off">
			<small class="form-hint">A name and a number are both required.</small>
			@error('emergency_contact_phone')<small class="form-error">{{ $message }}</small>@enderror
		</div>
	</div>

	<div class="event-actions"><button class="button" type="submit" data-loading-label="Updating...">Update Athlete</button><a class="button button-muted" href="{{ route('athletes.show', $athlete) }}">Cancel</a></div>
</form>
@endsection