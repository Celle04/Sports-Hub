@if ($coach)
	<div class="card">
		<div class="profile-hero">
			<div class="avatar">{{ strtoupper(substr($coach->name, 0, 2)) }}</div>
			<div>
				<h2>{{ $coach->name }}</h2>
				<p>
					{{ $coach->specialty }}
					@if ($coach->sport)
						<br>{{ $coach->sport->name }} Coach
					@endif
				</p>
			</div>
		</div>
		<div class="grid grid-2" style="margin-top:20px">
			<div class="card soft-card">Email<br><strong>{{ $coach->email }}</strong></div>
			<div class="card soft-card">Phone<br><strong>{{ $coach->phone ?: 'Not provided' }}</strong></div>
		</div>
	</div>
@else
	<div class="card"><p>A coach has not yet been assigned to your sport.</p></div>
@endif
