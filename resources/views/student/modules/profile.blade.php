<div class="student-profile">
	<header class="student-profile-hero">
		<div class="student-profile-identity">
			<x-avatar :user="$athlete" size="xl" id="profile-photo-preview" class="student-profile-avatar" />
			<div class="student-profile-name">
				<span>ATHLETE PROFILE</span>
				<h2>{{ $athlete->name }}</h2>
				<p>{{ $athlete->role === 'Student' ? 'Athlete' : $athlete->role }} &middot; {{ $athlete->sport?->name ?? 'Unassigned sport' }}</p>
			</div>
		</div>
		<div class="student-profile-id">
			<span>STUDENT ID</span>
			<strong>{{ $athlete->student_id ?: 'Not recorded' }}</strong>
			<span>STATUS</span>
			<strong>{{ $athlete->status ?? 'Active' }}</strong>
		</div>
	</header>

	<div class="student-profile-columns">
		<form class="student-profile-column" method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data">
			@csrf
			@method('PATCH')

			<section class="student-profile-section">
				<header class="student-profile-section-heading">
					<span>PERSONAL INFORMATION</span>
					<h3>Your account details</h3>
				</header>

				<div class="student-profile-edit-grid">
					<div class="form-group">
						<label for="profile-name">Full name</label>
						<input class="form-control @error('name') is-invalid @enderror" id="profile-name" name="name" type="text" value="{{ old('name', $athlete->name) }}" autocomplete="name" required>
						@error('name')<small class="form-error">{{ $message }}</small>@enderror
					</div>
					<div class="form-group">
						<label for="profile-email">Email address</label>
						<input class="form-control @error('email') is-invalid @enderror" id="profile-email" name="email" type="email" value="{{ old('email', $athlete->email) }}" autocomplete="email" required>
						<small class="form-hint">You sign in with this email address.</small>
						@error('email')<small class="form-error">{{ $message }}</small>@enderror
					</div>
					<div class="form-group">
						<label for="profile-phone">Contact number</label>
						<input class="form-control @error('phone') is-invalid @enderror" id="profile-phone" name="phone" type="tel" value="{{ old('phone', $athlete->phone) }}" autocomplete="tel">
						@error('phone')<small class="form-error">{{ $message }}</small>@enderror
					</div>
					<div class="form-group">
						<label for="profile-grade-level">Grade level</label>
						<input class="form-control @error('grade_level') is-invalid @enderror" id="profile-grade-level" name="grade_level" type="text" value="{{ old('grade_level', $athlete->grade_level) }}" placeholder="{{ $studentApplication?->grade ?: 'e.g. Grade 10' }}">
						<small class="form-hint">@if ($athlete->grade_level)This is the grade level shown on your profile.@elseif ($studentApplication?->grade)From your application: {{ $studentApplication->grade }}. Add your own to update it.@else Your current grade and section.@endif</small>
						@error('grade_level')<small class="form-error">{{ $message }}</small>@enderror
					</div>
					<div class="form-group">
						<label for="profile-student-id">Student ID</label>
						<input class="form-control" id="profile-student-id" type="text" value="{{ $athlete->student_id ?: 'Not recorded' }}" readonly>
						<small class="form-hint">Managed by the Sports Coordinator.</small>
					</div>
					<div class="form-group">
						<label for="profile-sport">Sport</label>
						<input class="form-control" id="profile-sport" type="text" value="{{ $athlete->sport?->name ?? 'Unassigned sport' }}" readonly>
						<small class="form-hint">Assigned by the Sports Coordinator.</small>
					</div>
					<div class="form-group">
						<label for="profile-role">Role</label>
						<input class="form-control" id="profile-role" type="text" value="{{ $athlete->role === 'Student' ? 'Athlete' : $athlete->role }}" readonly>
						<small class="form-hint">Only an administrator can change your role.</small>
					</div>
				</div>
			</section>

			<section class="student-profile-section">
				<header class="student-profile-section-heading">
					<span>EMERGENCY CONTACT</span>
					<h3>Who to contact in an emergency</h3>
				</header>

				<div class="student-profile-edit-grid">
					<div class="form-group">
						<label for="profile-emergency-name">Contact name</label>
						<input class="form-control @error('emergency_contact_name') is-invalid @enderror" id="profile-emergency-name" name="emergency_contact_name" type="text" value="{{ old('emergency_contact_name', $athlete->emergency_contact_name) }}" autocomplete="off">
						<small class="form-hint">The person staff should contact first.</small>
						@error('emergency_contact_name')<small class="form-error">{{ $message }}</small>@enderror
					</div>
					<div class="form-group">
						<label for="profile-emergency-relationship">Relationship</label>
						<input class="form-control @error('emergency_contact_relationship') is-invalid @enderror" id="profile-emergency-relationship" name="emergency_contact_relationship" type="text" value="{{ old('emergency_contact_relationship', $athlete->emergency_contact_relationship) }}" placeholder="e.g. Mother" autocomplete="off">
						@error('emergency_contact_relationship')<small class="form-error">{{ $message }}</small>@enderror
					</div>
					<div class="form-group">
						<label for="profile-emergency-phone">Contact number</label>
						<input class="form-control @error('emergency_contact_phone') is-invalid @enderror" id="profile-emergency-phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone', $athlete->emergency_contact_phone) }}" autocomplete="off">
						<small class="form-hint">A name and a number are both required.</small>
						@error('emergency_contact_phone')<small class="form-error">{{ $message }}</small>@enderror
					</div>
				</div>
			</section>

			<section class="student-profile-section student-profile-photo-section">
				<header class="student-profile-section-heading">
					<span>PROFILE PHOTO</span>
					<h3>Personalize your profile</h3>
				</header>

				<div class="student-profile-photo-grid">
					<div class="form-group student-profile-photo-field">
						<label for="profile-photo">Upload a new photo</label>
						<input
							class="form-control @error('profile_photo') is-invalid @enderror"
							id="profile-photo"
							name="profile_photo"
							type="file"
							accept="image/jpeg,image/png,image/webp"
							data-photo-input
						>
						<small>JPG, JPEG, PNG or WEBP &middot; maximum 2 MB.</small>
						@error('profile_photo')<small class="form-error">{{ $message }}</small>@enderror
					</div>

					@if ($athlete->profile_photo_path)
						<div class="form-group student-profile-remove-field">
							<label for="remove-photo">Remove current photo</label>
							<label class="student-profile-check" for="remove-photo">
								<input id="remove-photo" name="remove_photo" type="checkbox" value="1" @checked(old('remove_photo'))>
								<span>Delete my current photo</span>
							</label>
						</div>
					@endif
				</div>
			</section>

			<button class="button student-profile-save" type="submit">Save Changes</button>
		</form>

		<div class="student-profile-column">
			<section class="student-profile-section">
				<header class="student-profile-section-heading">
					<span>ACCOUNT SECURITY</span>
					<h3>Change password</h3>
				</header>

				<form method="POST" action="{{ route('student.profile.password') }}" data-loading-label="Updating...">
					@csrf
					@method('PATCH')

					<div class="student-profile-password-grid">
						<div class="form-group">
							<label for="current-password">Current password</label>
							<span class="student-profile-control">
								<input class="form-control @error('current_password') is-invalid @enderror" id="current-password" name="current_password" type="password" autocomplete="current-password" required>
								<button class="student-profile-toggle" type="button" data-password-toggle="current-password" aria-label="Show password" aria-pressed="false">
									<svg aria-hidden="true"><use href="#icon-eye"></use></svg>
								</button>
							</span>
							@error('current_password')<small class="form-error">{{ $message }}</small>@enderror
						</div>

						<div class="form-group">
							<label for="new-password">New password</label>
							<span class="student-profile-control">
								<input class="form-control @error('password') is-invalid @enderror" id="new-password" name="password" type="password" minlength="8" autocomplete="new-password" required>
								<button class="student-profile-toggle" type="button" data-password-toggle="new-password" aria-label="Show password" aria-pressed="false">
									<svg aria-hidden="true"><use href="#icon-eye"></use></svg>
								</button>
							</span>
							<small class="form-hint">Minimum 8 characters.</small>
							@error('password')<small class="form-error">{{ $message }}</small>@enderror
						</div>

						<div class="form-group">
							<label for="new-password-confirmation">Confirm new password</label>
							<span class="student-profile-control">
								<input class="form-control" id="new-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
								<button class="student-profile-toggle" type="button" data-password-toggle="new-password-confirmation" aria-label="Show password" aria-pressed="false">
									<svg aria-hidden="true"><use href="#icon-eye"></use></svg>
								</button>
							</span>
						</div>
					</div>

					<button class="button student-profile-save" type="submit">Change Password</button>
				</form>
			</section>

			<section class="student-profile-section">
				<header class="student-profile-section-heading">
					<span>SCHOOL RECORD</span>
					<h3>From your application</h3>
				</header>
				<div class="student-profile-details">
					<div><span>Grade / Section on application</span><strong>{{ $studentApplication?->grade ?: 'Not recorded' }}</strong></div>
					<div><span>Grade level on profile</span><strong>{{ $athlete->gradeLevel() ?: 'Not recorded' }}</strong></div>
					<div><span>Gender</span><strong>{{ $studentApplication?->gender ?: 'Not recorded' }}</strong></div>
					<div><span>Application status</span><strong>{{ $studentApplication?->status ?: 'No application yet' }}</strong></div>
					<div><span>Email on file</span><strong>{{ $studentApplication?->email ?: $athlete->email }}</strong></div>
				</div>
			</section>
		</div>
	</div>

	@php($profileAchievements = $athlete->achievements()->with('sport')->orderByDesc('date_achieved')->orderByDesc('id')->get())

	<section class="student-profile-section student-profile-achievements">
		<header class="student-profile-section-heading">
			<span>ACHIEVEMENTS</span>
			<h3>Your accomplishments</h3>
		</header>

		@if ($profileAchievements->isEmpty())
			<p class="empty-state">No achievements yet. Your achievements and awards will appear here once they are recorded by the Sports Coordinator.</p>
		@else
			<div class="student-profile-achievement-list">
				@foreach ($profileAchievements->take(4) as $achievement)
					<a class="student-profile-achievement" href="{{ route('student.achievements.show', $achievement) }}">
						<span class="achievement-medal achievement-medal-{{ strtolower(str_replace(' ', '-', $achievement->achievement_type)) }} {{ $achievement->isMedal() ? '' : 'achievement-medal-plain' }}"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
						<span><strong>{{ $achievement->title }}</strong><small class="meta">{{ $achievement->achievement_type }} &middot; {{ $achievement->sport?->name ?? 'Unassigned sport' }} &middot; {{ $achievement->dateAchievedLabel() }}</small></span>
					</a>
				@endforeach
			</div>
			<a class="text-link" href="{{ route('student.achievements') }}">
				{{ $profileAchievements->count() > 4 ? 'View all '.$profileAchievements->count().' achievements' : 'View all achievements' }}
				<span aria-hidden="true">&rarr;</span>
			</a>
		@endif
	</section>
</div>

<script src="{{ asset('js/profile.js') }}" defer></script>