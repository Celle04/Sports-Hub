<div class="admin-dashboard">
    <section class="admin-dashboard-overview" aria-labelledby="admin-dashboard-overview-title">
        <div>
            <span class="admin-dashboard-eyebrow">ADMIN OVERVIEW</span>
            <h2 id="admin-dashboard-overview-title">Program at a glance</h2>
            <p>A quick view of the people, activities, and records across your sports program.</p>
        </div>
        <span class="admin-dashboard-overview-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M3 17.5 8.5 12l3.5 3.5L21 6.5M15 6.5h6v6" /></svg>
        </span>
    </section>

    <div class="grid dashboard-stats admin-dashboard-stats">
        @foreach ($stats ?? [] as $stat)
            <a class="card stat dashboard-stat-link admin-dashboard-stat" href="{{ route($stat['route']) }}">
                <span class="admin-dashboard-stat-heading">
                    <small>{{ $stat['label'] }}</small>
                    <span class="admin-dashboard-stat-icon" aria-hidden="true">
                        @switch($stat['label'])
                            @case('Total Sports')
                                <svg viewBox="0 0 24 24"><path d="M8 4h8v4a4 4 0 0 1-8 0V4ZM8 6H5v2a4 4 0 0 0 4 4M16 6h3v2a4 4 0 0 1-4 4M12 12v5m-4 3h8m-6-3h4v3H10v-3Z" /></svg>
                                @break
                            @case('Active Athletes')
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="7.5" r="3.5" /><path d="M5 21a7 7 0 0 1 14 0" /></svg>
                                @break
                            @case('Active Teams')
                                <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3" /><path d="M3 20a6 6 0 0 1 12 0m1-11a3 3 0 1 1 2 5.65M18 16a5 5 0 0 1 3 4" /></svg>
                                @break
                            @case('Upcoming Events')
                                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
                                @break
                            @case("Today's Sessions")
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                                @break
                            @case('Present Today')
                                <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3" /><path d="M3 20a6 6 0 0 1 12 0m1-5 2 2 4-4" /></svg>
                                @break
                            @case('Pending Check-ins')
                                <svg viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4.5V3h6v1.5M8 10h8m-8 4h4m3 2 1.5 1.5L21 15" /></svg>
                                @break
                            @case('Attendance Rate')
                                <svg viewBox="0 0 24 24"><path d="M4 20V11m5 9V5m5 15v-7m5 7V8m-17 13h20" /></svg>
                                @break
                            @case('Pending Applications')
                                <svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /><path d="M14 3v5h5m-9 4h5m-5 4h5" /></svg>
                                @break
                            @case('Medical Clearance')
                                <svg viewBox="0 0 24 24"><path d="M20.8 8.6c0 5.2-8.8 11-8.8 11S3.2 13.8 3.2 8.6A4.6 4.6 0 0 1 12 6.4a4.6 4.6 0 0 1 8.8 2.2Z" /><path d="M12 8v6m-3-3h6" /></svg>
                                @break
                        @endswitch
                    </span>
                </span>
                <strong>{{ $stat['value'] }}</strong>
            </a>
        @endforeach
    </div>

    <section class="card dashboard-quick-actions admin-dashboard-actions">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-section-kicker">GET THINGS DONE</span>
                <h2>Quick Actions</h2>
                <p class="meta">Jump directly to the most common management tasks.</p>
            </div>
        </div>
        <div class="quick-actions-grid">
            <a class="button" href="{{ route('athletes.index') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3" /><path d="M3 20a6 6 0 0 1 12 0M17 8h5M19.5 5.5v5" /></svg>
                <span>Add Athlete</span>
            </a>
            <a class="button" href="{{ route('sports.create') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3 21 8v8l-9 5-9-5V8l9-5Z" /><path d="m3.5 8.5 8.5 5 8.5-5M12 13.5V21" /></svg>
                <span>Add Sport</span>
            </a>
            <a class="button" href="{{ route('events.create') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18M12 13v5M9.5 15.5h5" /></svg>
                <span>Create Event</span>
            </a>
            <a class="button" href="{{ route('admin.applications') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4.5V3h6v1.5M8 10h8M8 14h5M15 17l2 2 4-4" /></svg>
                <span>Review Applications</span>
            </a>
            <a class="button" href="{{ route('admin.medical.create') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 3h6v5h5v6h-5v5H9v-5H4V8h5V3Z" /></svg>
                <span>Add Medical Record</span>
            </a>
            <a class="button" href="{{ route('admin.attendance') }}">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 12l2.5 2.5L16 9" /><path d="M8 2v4M16 2v4" /></svg>
                <span>Record Attendance</span>
            </a>
        </div>
    </section>

    <div class="grid grid-2 admin-dashboard-panels">
        <section class="card admin-dashboard-panel">
            <div class="section-heading">
                <div>
                    <span class="admin-dashboard-section-kicker">ON THE CALENDAR</span>
                    <h2>Upcoming Events</h2>
                    <p class="meta">The next scheduled sports activities.</p>
                </div>
            </div>
            @forelse ($upcomingEvents ?? [] as $event)
                <div class="admin-dashboard-list-item">
                    <span class="admin-dashboard-list-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
                    </span>
                    <div>
                        <strong>{{ $event->title }}</strong>
                        <p>{{ $event->starts_at?->format('M j, Y') }} <span aria-hidden="true">&middot;</span> {{ $event->sport?->name ?? 'General' }}</p>
                    </div>
                </div>
            @empty
                <p class="empty-state admin-dashboard-empty">No upcoming events scheduled.</p>
            @endforelse
        </section>

        <section class="card admin-dashboard-panel">
            <div class="section-heading">
                <div>
                    <span class="admin-dashboard-section-kicker">NEEDS YOUR REVIEW</span>
                    <h2>Pending Sports Applications</h2>
                    <p class="meta">Fresh applications waiting for review.</p>
                </div>
            </div>
            @forelse ($pendingApplications ?? [] as $application)
                <div class="admin-dashboard-list-item">
                    <span class="admin-dashboard-list-icon admin-dashboard-list-icon-warm" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3" /><path d="M3 20a6 6 0 0 1 12 0M17 8h5M19.5 5.5v5" /></svg>
                    </span>
                    <div>
                        <strong>{{ $application->name }}</strong>
                        <p>{{ $application->sport?->name ?? $application->sport ?? 'Sport not set' }}</p>
                    </div>
                </div>
            @empty
                <p class="empty-state admin-dashboard-empty">No applications are currently pending review.</p>
            @endforelse
        </section>
    </div>

    <section class="card admin-dashboard-clearance">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-section-kicker">ATHLETE WELLNESS</span>
                <h2>Medical Clearance</h2>
                <p class="meta">Current athlete clearance status across the program.</p>
            </div>
        </div>
        <div class="admin-dashboard-clearance-grid">
            @forelse ($medicalStatusCounts ?? [] as $label => $count)
                <div class="admin-dashboard-clearance-item">
                    <span>{{ $label }}</span>
                    <strong>{{ $count }}</strong>
                </div>
            @empty
                <p class="empty-state admin-dashboard-empty">No medical clearance data available.</p>
            @endforelse
        </div>
    </section>
</div>
