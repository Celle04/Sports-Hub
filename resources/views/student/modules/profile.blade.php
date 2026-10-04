<form class="student-profile" method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data">
	@csrf
	@method('PATCH')

	<header class="student-profile-hero">
		<div class="student-profile-identity">
			@if ($athlete->profile_photo_path)
				<img class="student-profile-avatar" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($athlete->profile_photo_path) }}" alt="{{ $athlete->name }} profile photo">
			@else
				<div class="student-profile-avatar student-profile-initials" aria-hidden="true">{{ strtoupper(substr($athlete->name, 0, 2)) }}</div>
			@endif
			<div class="student-profile-name">
				<span>STUDENT PROFILE</span>
				<h2>{{ $athlete->name }}</h2>
				<p>{{ $athlete->sport?->name ?? 'Unassigned sport' }} athlete</p>
			</div>
		</div>
		<div class="student-profile-id"><span>STUDENT ID</span><strong>{{ $athlete->student_id ?: 'Not recorded' }}</strong></div>
	</header>

	<section class="student-profile-section">
		<header class="student-profile-section-heading"><span>YOUR RECORD</span><h3>School information</h3></header>
		<div class="student-profile-details">
			<div><span>Email address</span><strong>{{ $athlete->email }}</strong></div>
			<div><span>Sport</span><strong>{{ $athlete->sport?->name ?? 'Unassigned sport' }}</strong></div>
			<div><span>Grade / Section</span><strong>{{ $studentApplication?->grade ?: 'Not recorded' }}</strong></div>
			<div><span>Gender</span><strong>{{ $studentApplication?->gender ?: 'Not recorded' }}</strong></div>
		</div>
	</section>

	<section class="student-profile-section student-profile-edit-section">
		<header class="student-profile-section-heading"><span>KEEP IT CURRENT</span><h3>Contact and photo</h3></header>
		<div class="student-profile-edit-grid">
			<div class="form-group">
				<label for="student-profile-phone">Contact number</label>
				<input class="form-control" id="student-profile-phone" name="phone" type="tel" value="{{ old('phone', $athlete->phone) }}" autocomplete="tel">
			</div>
			<div class="form-group student-profile-photo-field">
				<label for="student-profile-photo">Profile photo</label>
				<input class="form-control" id="student-profile-photo" name="profile_photo" type="file" accept="image/*">
				<small>{{ $athlete->profile_photo_path ? 'Choose a new image to replace your current photo.' : 'Choose an image to personalize your profile.' }}</small>
			</div>
		</div>
		<button class="button student-profile-save" type="submit">Save profile</button>
	</section>
</form>
