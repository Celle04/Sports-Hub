<div class="grid dashboard-stats">
    @foreach ($stats ?? [] as $stat)
        <a class="card stat dashboard-stat-link" href="{{ route($stat['route']) }}">
            <small>{{ $stat['label'] }}</small>
            <strong>{{ $stat['value'] }}</strong>
            <span class="dashboard-stat-action">{{ $stat['action'] }} &rarr;</span>
        </a>
    @endforeach
</div>

<section class="card dashboard-quick-actions">
    <div class="section-heading"><div><h2>Quick Actions</h2><p class="meta">Jump directly to the most common management tasks.</p></div></div>
    <div class="quick-actions-grid">
        <a class="button" href="{{ route('athletes.index') }}">Add Athlete</a>
        <a class="button" href="{{ route('sports.create') }}">Add Sport</a>
        <a class="button" href="{{ route('events.create') }}">Create Event</a>
        <a class="button" href="{{ route('admin.applications') }}">Review Applications</a>
        <a class="button" href="{{ route('admin.medical.create') }}">Add Medical Record</a>
        <a class="button" href="{{ route('admin.attendance') }}">Record Attendance</a>
    </div>
</section>

<div class="grid grid-2" style="margin-top:18px;">
    <section class="card">
        <div class="section-heading"><div><h2>Upcoming Events</h2><p class="meta">The next scheduled sports activities.</p></div></div>
        @forelse ($upcomingEvents ?? [] as $event)
            <div class="event-preview"><strong>{{ $event->title }}</strong><p>{{ $event->starts_at?->format('M j, Y') }} &middot; {{ $event->sport?->name ?? 'General' }}</p></div>
        @empty
            <p class="empty-state">No upcoming events scheduled.</p>
        @endforelse
    </section>
    <section class="card">
        <div class="section-heading"><div><h2>Pending Sports Applications</h2><p class="meta">Fresh applications waiting for review.</p></div></div>
        @forelse ($pendingApplications ?? [] as $application)
            <div class="event-preview"><strong>{{ $application->name }}</strong><p>{{ $application->sport?->name ?? $application->sport ?? 'Sport not set' }}</p></div>
        @empty
            <p class="empty-state">No applications are currently pending review.</p>
        @endforelse
    </section>
</div>

<section class="card" style="margin-top:18px;">
    <div class="section-heading"><div><h2>Medical Clearance</h2><p class="meta">Current athlete clearance status across the program.</p></div></div>
    @foreach ($medicalStatusCounts ?? [] as $label => $count)
        <div class="event-preview" style="display:flex;justify-content:space-between;"><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
    @endforeach
</section>
