@php
    $certificateAchievement = $certificateAchievement ?? null;
    $certificateAchievements = $certificateAchievements ?? collect();
@endphp

<section class="card certificate-generator-card">
    <div class="section-heading">
        <div>
            <span class="admin-dashboard-kicker">CERTIFICATE GENERATOR</span>
            <h2>Generate Achievement Certificate</h2>
            <p>Choose an achievement to produce its official SportsHub certificate</p>
        </div>
        <a class="text-link" href="{{ route('admin.achievements') }}">Back to Achievements</a>
    </div>

    <form method="GET" action="{{ route('admin.achievements.certificate.generate') }}" data-auto-submit class="certificate-generator-form">
        <div class="form-group">
            <label for="certificate-achievement">Achievement</label>
            <select class="form-control" id="certificate-achievement" name="achievement">
                <option value="">Select an achievement</option>
                @foreach ($certificateAchievements as $option)
                    <option value="{{ $option->id }}" @selected($certificateAchievement?->id === $option->id)>
                        {{ $option->athlete?->name ?? 'Unknown athlete' }} &mdash; {{ $option->sportLabel() }} &mdash; {{ $option->title }}
                    </option>
                @endforeach
            </select>
            <small class="form-hint">The certificate pulls its details straight from the selected achievement record.</small>
        </div>
        <button class="button" type="submit">Generate Certificate</button>
    </form>
</section>

@if ($certificateAchievement)
    <section class="card certificate-preview-card">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">SELECTED ACHIEVEMENT</span>
                <h2>{{ $certificateAchievement->title }}</h2>
                <p>{{ $certificateAchievement->sportLabel() }} &middot; {{ $certificateAchievement->dateAchievedLabel() }}</p>
            </div>
        </div>

        <div class="achievement-detail-grid">
            <div><span>Athlete</span><strong>{{ $certificateAchievement->athlete?->name ?? 'Unknown athlete' }}</strong>@if ($certificateAchievement->athlete?->student_id)<small>Student ID {{ $certificateAchievement->athlete->student_id }}</small>@endif</div>
            <div><span>Type</span><strong>{{ $certificateAchievement->achievement_type }}</strong></div>
            <div><span>Sport</span><strong>{{ $certificateAchievement->sportLabel() }}</strong></div>
            <div><span>Place</span><strong>{{ $certificateAchievement->place ?: 'Not recorded' }}</strong></div>
            <div><span>Competition / Event</span><strong>{{ $certificateAchievement->competitionLabel() ?: 'Not recorded' }}</strong></div>
            <div><span>Date Achieved</span><strong>{{ $certificateAchievement->dateAchievedLabel() }}</strong></div>
        </div>

        <div class="certificate-preview-actions">
            <a class="button" href="{{ route('admin.achievements.certificate.print', $certificateAchievement) }}">Open Printable Certificate</a>
            <a class="button button-secondary" href="{{ route('admin.achievements.certificate.pdf', $certificateAchievement) }}">Download PDF</a>
        </div>
    </section>
@else
    <div class="card certificate-empty-card"><p class="empty-state">Select an achievement above to generate its certificate.</p></div>
@endif