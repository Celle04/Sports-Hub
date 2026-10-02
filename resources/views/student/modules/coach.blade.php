@if ($coach)
	<section class="student-coach-page">
		<div class="student-coach-profile">
			<div class="student-coach-identity">
				<div class="student-coach-avatar" aria-hidden="true">{{ strtoupper(substr($coach->name, 0, 2)) }}</div>
				<div class="student-coach-copy">
					<span class="student-coach-eyebrow">YOUR SPORTS COACH</span>
					<h2>{{ $coach->name }}</h2>
					<p>{{ $coach->specialty ?: 'Sports coach' }}{{ $coach->sport ? ' · '.$coach->sport->name : '' }}</p>
				</div>
			</div>
			<span class="student-coach-assigned"><i></i> Assigned to your sport</span>
		</div>

		<section class="student-coach-contact" aria-label="Coach contact information">
			<header><span>GET IN TOUCH</span><h3>Contact your coach</h3></header>
			<div class="student-coach-contact-grid">
				<div class="student-coach-contact-item">
					<span class="student-coach-contact-mark" aria-hidden="true"><svg><use href="#icon-clipboard"></use></svg></span>
					<div><small>EMAIL</small><a href="mailto:{{ $coach->email }}">{{ $coach->email }}</a></div>
				</div>
				<div class="student-coach-contact-item">
					<span class="student-coach-contact-mark student-coach-contact-mark-green" aria-hidden="true"><svg><use href="#icon-user"></use></svg></span>
					<div><small>PHONE</small>
						@if ($coach->phone)
							<a href="tel:{{ preg_replace('/[^0-9+]/', '', $coach->phone) }}">{{ $coach->phone }}</a>
						@else
						<strong>Not provided</strong>
						@endif
					</div>
				</div>
			</div>
		</section>
	</section>
@else
	<div class="student-coach-unassigned">
		<span aria-hidden="true"><svg><use href="#icon-users"></use></svg></span>
		<strong>No coach assigned yet</strong>
		<p>A coach has not yet been assigned to your sport.</p>
	</div>
@endif
