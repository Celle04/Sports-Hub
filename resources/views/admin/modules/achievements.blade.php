@php
    $achievementSummary = $achievementSummary ?? ['total' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'titles' => 0, 'athletes' => 0, 'this_year' => 0];
    $achievementFormMode = $achievementFormMode ?? 'create';
    $achievementFormRecord = $achievementFormRecord ?? null;
    $selectedAchievement = $selectedAchievement ?? null;
    $achievementViewMode = $achievementViewMode ?? 'index';
    $achievementTypes = $achievementTypes ?? \App\Models\Achievement::TYPES;
@endphp

<div class="grid achievement-summary-grid">
    <div class="card stat"><small>Total Achievements</small><strong>{{ $achievementSummary['total'] }}</strong></div>
    <div class="card stat"><small>Gold Medals</small><strong>{{ $achievementSummary['gold'] }}</strong></div>
    <div class="card stat"><small>Silver Medals</small><strong>{{ $achievementSummary['silver'] }}</strong></div>
    <div class="card stat"><small>Bronze Medals</small><strong>{{ $achievementSummary['bronze'] }}</strong></div>
    <div class="card stat"><small>Championships</small><strong>{{ $achievementSummary['titles'] }}</strong></div>
    <div class="card stat"><small>Athletes Awarded</small><strong>{{ $achievementSummary['athletes'] }}</strong></div>
</div>

@if ($achievementViewMode === 'show' && $selectedAchievement)
    <section class="card achievement-detail-card">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">ACHIEVEMENT RECORD</span>
                <h2>{{ $selectedAchievement->title }}</h2>
                <p>Recorded {{ $selectedAchievement->dateAchievedLabel() }}</p>
            </div>
            <a class="text-link" href="{{ route('admin.achievements') }}">Back to list</a>
        </div>

        <div class="achievement-detail-grid">
            <div><span>Athlete</span><strong>{{ $selectedAchievement->athlete?->name ?? 'Unknown athlete' }}</strong>@if ($selectedAchievement->athlete?->student_id)<small>Student ID {{ $selectedAchievement->athlete->student_id }}</small>@endif</div>
            <div><span>Type</span><strong><span class="badge {{ $selectedAchievement->isMedal() ? 'achievement-medal-'.strtolower(str_replace(' ', '-', $selectedAchievement->achievement_type)) : '' }}">{{ $selectedAchievement->achievement_type }}</span></strong></div>
            <div><span>Sport</span><strong>{{ $selectedAchievement->sportLabel() }}</strong></div>
            <div><span>Place</span><strong>{{ $selectedAchievement->place ?: 'Not recorded' }}</strong></div>
            <div><span>Competition / Event</span><strong>{{ $selectedAchievement->competitionLabel() ?: 'Not recorded' }}</strong>@if ($selectedAchievement->event)<small><a class="text-link" href="{{ route('events.show', $selectedAchievement->event) }}">Open linked event</a></small>@endif</div>
            <div><span>Date Achieved</span><strong>{{ $selectedAchievement->dateAchievedLabel() }}</strong></div>
        </div>

        @if ($selectedAchievement->description)
            <div class="achievement-detail-description">
                <span>Description</span>
                <p>{{ $selectedAchievement->description }}</p>
            </div>
        @endif

        <div class="achievement-detail-actions">
            @if ($selectedAchievement->hasCertificate())
                <a class="button button-secondary" href="{{ route('admin.achievements.certificate', $selectedAchievement) }}">View Certificate</a>
            @else
                <span class="meta">No certificate attached.</span>
            @endif
            <a class="button" href="{{ route('admin.achievements.certificate.generate', ['achievement' => $selectedAchievement->id]) }}">Generate Certificate</a>
            <a class="button button-secondary" href="{{ route('admin.achievements.edit', $selectedAchievement) }}">Edit</a>
            <button class="text-link" type="button" data-confirm-dialog data-confirm-title="Delete Achievement?" data-confirm-message="Delete this achievement? This action cannot be undone." data-confirm-label="Delete" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.achievements.destroy', $selectedAchievement) }}">Delete</button>
        </div>
    </section>
@else
    <section class="card achievement-form-card">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">{{ $achievementFormMode === 'edit' ? 'UPDATE RECORD' : 'NEW RECORD' }}</span>
                <h2>{{ $achievementFormMode === 'edit' ? 'Edit Achievement' : 'Add Achievement' }}</h2>
                <p>Record an accomplishment for an athlete. The athlete sees it immediately on their dashboard.</p>
            </div>
            @if ($achievementFormMode === 'edit')
                <a class="text-link" href="{{ route('admin.achievements') }}">Cancel</a>
            @endif
        </div>

        <form method="POST" action="{{ $achievementFormMode === 'edit' ? route('admin.achievements.update', $achievementFormRecord) : route('admin.achievements.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($achievementFormMode === 'edit') @method('PUT') @endif

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="achievement-athlete">Athlete</label>
                    <select class="form-control @error('athlete_id') is-invalid @enderror" id="achievement-athlete" name="athlete_id" required>
                        <option value="">Select athlete</option>
                        @foreach ($achievementAthletes ?? [] as $option)
                            <option value="{{ $option->id }}" @selected((string) old('athlete_id', $achievementFormRecord?->athlete_id ?? '') === (string) $option->id)>
                                {{ $option->name }}@if ($option->student_id) ({{ $option->student_id }})@endif &mdash; {{ $option->sport?->name ?? 'Unassigned sport' }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-hint">Name, student ID and current sport are shown so the right athlete is picked.</small>
                    @error('athlete_id')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="achievement-sport">Sport</label>
                    <select class="form-control @error('sport_id') is-invalid @enderror" id="achievement-sport" name="sport_id">
                        <option value="">Use the athlete's sport</option>
                        @foreach ($achievementSports ?? [] as $option)
                            <option value="{{ $option->id }}" @selected((string) old('sport_id', $achievementFormRecord?->sport_id ?? '') === (string) $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-hint">Required for athletes who compete in more than one sport.</small>
                    @error('sport_id')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="grid grid-2" style="margin-top:12px">
                <div class="form-group">
                    <label for="achievement-title">Achievement Title</label>
                    <input class="form-control @error('title') is-invalid @enderror" id="achievement-title" name="title" type="text" value="{{ old('title', $achievementFormRecord?->title ?? '') }}" placeholder="e.g. Basketball Championship 2026" maxlength="150" required>
                    @error('title')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="achievement-type">Achievement Type</label>
                    <input class="form-control @error('achievement_type') is-invalid @enderror" id="achievement-type" name="achievement_type" type="text" list="achievement-type-options" value="{{ old('achievement_type', $achievementFormRecord?->achievement_type ?? '') }}" placeholder="Select or type a custom type" maxlength="60" required>
                    <datalist id="achievement-type-options">
                        @foreach ($achievementTypes as $type)<option value="{{ $type }}"></option>@endforeach
                    </datalist>
                    <small class="form-hint">Pick one of the standard categories or type your own.</small>
                    @error('achievement_type')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="grid grid-3" style="margin-top:12px">
                <div class="form-group">
                    <label for="achievement-competition">Competition</label>
                    <input class="form-control @error('competition') is-invalid @enderror" id="achievement-competition" name="competition" type="text" value="{{ old('competition', $achievementFormRecord?->competition ?? '') }}" placeholder="e.g. Caraga Regional Athletic Games 2026" maxlength="150">
                    @error('competition')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="achievement-event">Link Existing Event</label>
                    <select class="form-control @error('event_id') is-invalid @enderror" id="achievement-event" name="event_id">
                        <option value="">No linked event</option>
                        @foreach ($achievementEvents ?? [] as $option)
                            <option value="{{ $option->id }}" @selected((string) old('event_id', $achievementFormRecord?->event_id ?? '') === (string) $option->id)>
                                {{ $option->title }}@if ($option->sport) ({{ $option->sport->name }})@endif
                            </option>
                        @endforeach
                    </select>
                    <small class="form-hint">Optional. Links the achievement to an existing event instead of duplicating it.</small>
                    @error('event_id')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="achievement-date">Date Achieved</label>
                    <input class="form-control @error('date_achieved') is-invalid @enderror" id="achievement-date" name="date_achieved" type="date" value="{{ old('date_achieved', $achievementFormRecord?->date_achieved?->format('Y-m-d') ?? '') }}" required>
                    @error('date_achieved')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="grid grid-2" style="margin-top:12px">
                <div class="form-group">
                    <label for="achievement-place">Place / Rank</label>
                    <input class="form-control @error('place') is-invalid @enderror" id="achievement-place" name="place" type="text" value="{{ old('place', $achievementFormRecord?->place ?? '') }}" placeholder="e.g. 1st Place" maxlength="60">
                    @error('place')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="achievement-certificate">Certificate / Image</label>
                    <input class="form-control @error('certificate') is-invalid @enderror" id="achievement-certificate" name="certificate" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                    <small class="form-hint">JPG, JPEG, PNG, WEBP or PDF &middot; maximum 2 MB.@if ($achievementFormRecord?->hasCertificate()) A certificate is already attached and will be replaced.@endif</small>
                    @error('certificate')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="form-group" style="margin-top:12px">
                <label for="achievement-description">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="achievement-description" name="description" rows="3" maxlength="2000" placeholder="Optional details about the accomplishment">{{ old('description', $achievementFormRecord?->description ?? '') }}</textarea>
                @error('description')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <button class="button" type="submit">{{ $achievementFormMode === 'edit' ? 'Update Achievement' : 'Save Achievement' }}</button>
        </form>
    </section>

    <section class="card achievement-records-card">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">ALL RECORDS</span>
                <h2>Achievements</h2>
                <p>{{ count($achievements ?? []) }} record{{ count($achievements ?? []) === 1 ? '' : 's' }} matching the current filters</p>
            </div>
            <a class="button button-secondary" href="{{ route('admin.achievements.certificate.generate') }}">Generate Certificate</a>
        </div>

        <form method="GET" action="{{ route('admin.achievements') }}" class="filters achievement-filters">
            <input class="form-control" name="search" value="{{ $achievementSearch ?? '' }}" placeholder="Search athlete, student ID, title or competition" aria-label="Search achievements">
            <select class="form-control" name="athlete_id" aria-label="Filter by athlete">
                <option value="">All Athletes</option>
                @foreach ($achievementAthletes ?? [] as $option)
                    <option value="{{ $option->id }}" @selected((string) ($achievementAthleteFilter ?? '') === (string) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
            <select class="form-control" name="sport_id" aria-label="Filter by sport">
                <option value="">All Sports</option>
                @foreach ($achievementSports ?? [] as $option)
                    <option value="{{ $option->id }}" @selected((string) ($achievementSportFilter ?? '') === (string) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
            <select class="form-control" name="achievement_type" aria-label="Filter by achievement type">
                <option value="">All Types</option>
                @foreach ($achievementTypes as $type)
                    <option value="{{ $type }}" @selected(($achievementTypeFilter ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <input class="form-control" name="date_from" type="date" value="{{ $achievementDateFrom ?? '' }}" aria-label="Achieved from" title="Achieved from">
            <input class="form-control" name="date_to" type="date" value="{{ $achievementDateTo ?? '' }}" aria-label="Achieved to" title="Achieved to">
            <select class="form-control" name="sort" aria-label="Sort achievements">
                <option value="newest" @selected(($achievementSort ?? 'newest') === 'newest')>Newest first</option>
                <option value="oldest" @selected(($achievementSort ?? '') === 'oldest')>Oldest first</option>
                <option value="title" @selected(($achievementSort ?? '') === 'title')>Title A-Z</option>
            </select>
            <button class="button" type="submit">Search</button>
            <a class="button button-secondary" href="{{ route('admin.achievements') }}">Reset</a>
        </form>

        <div class="table-wrap">
            <table class="data-table achievement-table">
                <thead><tr><th>Athlete</th><th>Sport</th><th>Achievement</th><th>Type</th><th>Competition / Event</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($achievements ?? [] as $record)
                        <tr>
                            <td>
                                <strong>{{ $record->athlete?->name ?? 'Unknown athlete' }}</strong>
                                @if ($record->athlete?->student_id)<br><small class="meta">{{ $record->athlete->student_id }}</small>@endif
                            </td>
                            <td>{{ $record->sportLabel() }}</td>
                            <td>{{ $record->title }}@if ($record->certificate_path)<span class="badge achievement-cert-badge">Certificate</span>@endif</td>
                            <td><span class="badge {{ $record->isMedal() ? 'achievement-medal-'.strtolower(str_replace(' ', '-', $record->achievement_type)) : '' }}">{{ $record->achievement_type }}</span></td>
                            <td>{{ $record->competitionLabel() ?: 'Not recorded' }}</td>
                            <td>{{ $record->dateAchievedLabel() }}</td>
                            <td>
                                <a class="text-link" href="{{ route('admin.achievements.show', $record) }}">View</a>
                                <a class="text-link" href="{{ route('admin.achievements.edit', $record) }}">Edit</a>
                                <a class="text-link" href="{{ route('admin.achievements.certificate.generate', ['achievement' => $record->id]) }}">Certificate</a>
                                <button class="text-link" type="button" data-confirm-dialog data-confirm-title="Delete Achievement?" data-confirm-message="Delete this achievement? This action cannot be undone." data-confirm-label="Delete" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.achievements.destroy', $record) }}">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">
                                <p class="empty-state">No achievements found.</p>
                                <a class="button button-secondary" href="{{ route('admin.achievements.create') }}">Add Achievement</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif