<section class="student-sports">
    <header class="student-sports-heading">
        <div>
            <span class="student-sports-kicker">SNNHS ATHLETICS</span>
            <h2>Explore sports programs</h2>
            <p>Meet the programs and coaches building our teams.</p>
        </div>
        <span class="student-sports-count">{{ ($studentSports ?? collect())->count() }} {{ ($studentSports ?? collect())->count() === 1 ? 'program' : 'programs' }}</span>
    </header>

    <div class="student-sports-grid">
        @forelse ($studentSports ?? [] as $sport)
            <article class="card student-sport-card">
                <div class="student-sport-card-top">
                    <span class="student-sport-icon" aria-hidden="true"><svg><use href="#icon-trophy"></use></svg></span>
                    <span class="student-sport-classification">{{ $sport->classification ?? 'Sports program' }}</span>
                </div>
                <h3>{{ $sport->name }}</h3>
                <p class="student-sport-description">{{ $sport->description ?: 'Program details will be available soon.' }}</p>
                <div class="student-sport-coach">
                    <svg aria-hidden="true"><use href="#icon-user"></use></svg>
                    <span><small>PROGRAM COACH</small><strong>{{ $sport->coaches->first()?->name ?? 'Not assigned' }}</strong></span>
                </div>
            </article>
        @empty
            <p class="student-sports-empty">No active sports programs are available.</p>
        @endforelse
    </div>
</section>
