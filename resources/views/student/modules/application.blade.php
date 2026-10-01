<section class="card">
    <div class="section-heading">
        <div>
            <h2>My Sports Application</h2>
            <p class="meta">Application information and the latest administrator review.</p>
        </div>
    </div>

    @if ($studentApplication)
        <div class="grid grid-2">
            <div><small class="meta">Applicant</small><strong>{{ $studentApplication->name }}</strong></div>
            <div><small class="meta">Student ID</small><strong>{{ $studentApplication->student_id ?? 'Not recorded' }}</strong></div>
            <div><small class="meta">Sport</small><strong>{{ $studentApplication->sportCategory?->name ?? $studentApplication->sport ?? 'Not assigned' }}</strong></div>
            <div><small class="meta">Grade</small><strong>{{ $studentApplication->grade ?: 'Not recorded' }}</strong></div>
            <div><small class="meta">Status</small><strong><span class="badge">{{ $studentApplication->status }}</span></strong></div>
            <div><small class="meta">Submitted</small><strong>{{ $studentApplication->created_at?->format('M j, Y') }}</strong></div>
        </div>

        @if ($studentApplication->review_notes)
            <div class="event-preview"><strong>Administrator feedback</strong><p>{{ $studentApplication->review_notes }}</p></div>
        @endif
        @if ($studentApplication->rejection_reason)
            <div class="alert-banner alert-danger"><strong>Review note</strong><p>{{ $studentApplication->rejection_reason }}</p></div>
        @endif
    @else
        <p class="empty-state">No sports application is linked to your account yet.</p>
    @endif
</section>
