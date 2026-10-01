<div class="card">
	<form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data">
		@csrf
		@method('PATCH')

		<div class="profile-hero">
			<div class="avatar">{{ strtoupper(substr($athlete->name, 0, 2)) }}</div>
			<div>
				<h2>{{ $athlete->name }}</h2>
				<p>{{ $athlete->sport?->name ?? 'Unassigned sport' }} Athlete</p>
			</div>
		</div>

		<div class="form-grid">
			<div class="form-group">
				<label>Student ID</label>
				<input class="form-control" value="{{ $athlete->student_id ?: 'Not recorded' }}" readonly>
			</div>
			<div class="form-group">
				<label>Email</label>
				<input class="form-control" value="{{ $athlete->email }}" readonly>
			</div>
			<div class="form-group">
				<label>Sport</label>
				<input class="form-control" value="{{ $athlete->sport?->name ?? 'Unassigned sport' }}" readonly>
			</div>
			<div class="form-group">
				<label>Grade / Section</label>
				<input class="form-control" value="{{ $studentApplication?->grade ?: 'Not recorded' }}" readonly>
			</div>
			<div class="form-group">
				<label>Gender</label>
				<input class="form-control" value="{{ $studentApplication?->gender ?: 'Not recorded' }}" readonly>
			</div>
			<div class="form-group">
				<label>Contact number</label>
				<input class="form-control" name="phone" value="{{ old('phone', $athlete->phone) }}">
			</div>
			<div class="form-group">
				<label>Profile photo</label>
				<input class="form-control" name="profile_photo" type="file" accept="image/*">
			</div>
		</div>

		<button class="button" type="submit">Save profile</button>
	</form>
</div>
