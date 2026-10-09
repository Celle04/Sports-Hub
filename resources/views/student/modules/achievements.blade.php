@php
	$achievementSummary = $achievementSummary ?? ['total' => 0, 'medals' => 0, 'titles' => 0, 'sports' => 0];
	$selectedAchievement = $selectedAchievement ?? null;
	$achievements = $achievements ?? collect();
	$achievementTimeline = $achievementTimeline ?? collect();
	$certificateRequests = $certificateRequests ?? collect();
@endphp

@if ($selectedAchievement)
	<section class="card achievement-show-card">
		<div class="section-heading">
			<div>
				<span class="admin-dashboard-kicker">ACHIEVEMENT</span>
				<h2>{{ $selectedAchievement->title }}</h2>
				<p>{{ $selectedAchievement->sportLabel() }} &middot; {{ $selectedAchievement->dateAchievedLabel() }}</p>
			</div>
			<a class="text-link" href="{{ route('student.achievements') }}">All achievements</a>
		</div>

		<div class="achievement-show-hero">
			<span class="achievement-medal achievement-medal-{{ strtolower(str_replace(' ', '-', $selectedAchievement->achievement_type)) }} {{ $selectedAchievement->isMedal() ? '' : 'achievement-medal-plain' }}">
				<svg aria-hidden="true"><use href="#icon-trophy"></use></svg>
			</span>
			<div>
				<span class="badge">{{ $selectedAchievement->achievement_type }}</span>
				@if ($selectedAchievement->place)<p class="achievement-show-place">{{ $selectedAchievement->place }}</p>@endif
			</div>
		</div>

		<div class="achievement-detail-grid">
			<div><span>Sport</span><strong>{{ $selectedAchievement->sportLabel() }}</strong></div>
			<div><span>Date Achieved</span><strong>{{ $selectedAchievement->dateAchievedLabel() }}</strong></div>
			<div><span>Place</span><strong>{{ $selectedAchievement->place ?: 'Not recorded' }}</strong></div>
			<div><span>Competition / Event</span><strong>{{ $selectedAchievement->competitionLabel() ?: 'Not recorded' }}</strong></div>
		</div>

		@if ($selectedAchievement->description)
			<div class="achievement-detail-description">
				<span>Description</span>
				<p>{{ $selectedAchievement->description }}</p>
			</div>
		@endif

		<div class="achievement-detail-actions">
			@php($certificateRequest = $certificateRequests[$selectedAchievement->id] ?? null)
			@if ($selectedAchievement->hasCertificate())
				<a class="button" href="{{ route('student.achievements.certificate', $selectedAchievement) }}">View Certificate</a>
				<span class="badge status-issued">Certificate issued</span>
			@elseif ($certificateRequest && in_array($certificateRequest->status, ['Pending', 'Approved'], true))
				<span class="badge status-{{ strtolower($certificateRequest->status) }}">Certificate requested &middot; {{ $certificateRequest->status }}</span>
			@else
				@if ($certificateRequest && $certificateRequest->status === 'Rejected')
					<span class="meta">Your previous request was declined. @if ($certificateRequest->remarks)Reason: {{ $certificateRequest->remarks }} @endif</span>
				@endif
				<form method="POST" action="{{ route('student.achievements.certificate-request', $selectedAchievement) }}">
					@csrf
					<button class="button" type="submit">Request Certificate</button>
				</form>
			@endif
		</div>
	</section>
@else
	<div class="student-achievement-summary">
		<div class="card student-achievement-summary-card">
			<span class="student-achievement-summary-icon"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
			<div><small>TOTAL ACHIEVEMENTS</small><strong>{{ $achievementSummary['total'] }}</strong></div>
		</div>
		<div class="card student-achievement-summary-card">
			<span class="student-achievement-summary-icon student-achievement-summary-gold"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
			<div><small>MEDALS</small><strong>{{ $achievementSummary['medals'] }}</strong></div>
		</div>
		<div class="card student-achievement-summary-card">
			<span class="student-achievement-summary-icon student-achievement-summary-green"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
			<div><small>CHAMPIONSHIPS</small><strong>{{ $achievementSummary['titles'] }}</strong></div>
		</div>
	</div>

	@if ($achievements->isEmpty())
		<section class="card student-achievement-empty">
			<span class="student-achievement-empty-icon"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
			<h2>No achievements yet.</h2>
			<p>Your achievements and awards will appear here once they are recorded by the Sports Coordinator.</p>
		</section>
	@else
		<div class="student-achievement-timeline">
			@foreach ($achievementTimeline as $year => $yearAchievements)
				<section class="card student-achievement-year">
					<header class="student-dashboard-heading">
						<div><span class="student-dashboard-kicker">YEAR</span><h2>{{ $year ?: 'Date not recorded' }}</h2></div>
						<span class="badge">{{ $yearAchievements->count() }} award{{ $yearAchievements->count() === 1 ? '' : 's' }}</span>
					</header>

					<div class="student-achievement-cards">
						@foreach ($yearAchievements as $achievement)
							<a class="student-achievement-card" href="{{ route('student.achievements.show', $achievement) }}">
								<span class="achievement-medal achievement-medal-{{ strtolower(str_replace(' ', '-', $achievement->achievement_type)) }} {{ $achievement->isMedal() ? '' : 'achievement-medal-plain' }}">
									<svg aria-hidden="true"><use href="#icon-trophy"></use></svg>
								</span>
								<div class="student-achievement-card-body">
									<span class="badge">{{ $achievement->achievement_type }}</span>
									<h3>{{ $achievement->title }}</h3>
									<p>{{ $achievement->competitionLabel() ?: 'No competition recorded' }}</p>
									<div class="meta">{{ $achievement->sportLabel() }} &middot; {{ $achievement->dateAchievedLabel() }}@if ($achievement->place) &middot; {{ $achievement->place }}@endif</div>
									@if ($achievement->hasCertificate())
										<span class="student-achievement-certificate">Certificate available</span>
									@elseif (($certificateRequests[$achievement->id] ?? null)?->isOpen())
										<span class="student-achievement-certificate">Certificate requested</span>
									@endif
								</div>
							</a>
						@endforeach
					</div>
				</section>
			@endforeach
		</div>
	@endif
@endif