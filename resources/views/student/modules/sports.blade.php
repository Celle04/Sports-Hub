<section class="card">
    <div class="section-heading">
        <div>
            <h2>Sports Programs</h2>
            <p class="meta">Programs and coaches currently managed by SNNHS.</p>
        </div>
    </div>

    <div class="grid grid-2">
        @forelse ($studentSports ?? [] as $sport)
            <article class="card soft-card">
                <h3>{{ $sport->name }}</h3>
                <p class="meta">{{ $sport->classification ?? 'Sports program' }}</p>
                <p>{{ $sport->description ?: 'No program description available.' }}</p>
                <p class="meta">
                    Coach:
                    {{ $sport->coaches->first()?->name ?? 'Not assigned' }}
                </p>
            </article>
        @empty
            <p class="empty-state">No active sports programs are available.</p>
        @endforelse
    </div>
</section>
