<section class="student-application-page">
    <header class="student-application-heading">
        <div><span>SPORTSHUB MEMBERSHIP</span><h2>My application</h2><p>Application details and the latest administrator review.</p></div>
        <svg aria-hidden="true"><use href="#icon-clipboard"></use></svg>
    </header>

    @if ($studentApplication)
        @php($applicationStatusClass = 'application-status-'.strtolower(str_replace(' ', '-', $studentApplication->status)))
        <section class="card student-application-overview">
            <div class="student-application-status-row">
                <div><span class="student-application-label">APPLICATION STATUS</span><span class="badge {{ $applicationStatusClass }}">{{ $studentApplication->status }}</span></div>
                <div class="student-application-submitted"><span>SUBMITTED</span><strong>{{ $studentApplication->created_at?->format('M j, Y') ?? 'Date not recorded' }}</strong></div>
            </div>
            <div class="student-application-details">
                <div><span>Applicant</span><strong>{{ $studentApplication->name }}</strong></div>
                <div><span>Student ID</span><strong>{{ $studentApplication->student_id ?? 'Not recorded' }}</strong></div>
                <div><span>Sport</span><strong>{{ $studentApplication->sportCategory?->name ?? $studentApplication->sport ?? 'Not assigned' }}</strong></div>
                <div><span>Grade</span><strong>{{ $studentApplication->grade ?: 'Not recorded' }}</strong></div>
            </div>
        </section>

        @if ($studentApplication->review_notes)
            <section class="student-application-feedback">
                <span>ADMINISTRATOR FEEDBACK</span>
                <p>{{ $studentApplication->review_notes }}</p>
            </section>
        @endif
        @if ($studentApplication->rejection_reason)
            <section class="student-application-rejection">
                <strong>Review note</strong>
                <p>{{ $studentApplication->rejection_reason }}</p>
            </section>
        @endif
    @else
        <div class="student-application-empty">
            <span aria-hidden="true"><svg><use href="#icon-clipboard"></use></svg></span>
            <strong>No application linked</strong>
            <p>No sports application is linked to your account yet.</p>
        </div>
    @endif
</section>
