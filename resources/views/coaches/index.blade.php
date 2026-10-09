@extends('layouts.portal')

@section('content')
    @php
        $hasFilters = request()->hasAny(['search', 'sport_id', 'coach_type', 'status']);
    @endphp

    <div class="module-header">
        <div>
            <h1>Coach Management</h1>
            <p class="page-subtitle">Manage coaches, coaching assignments, specialties, and team responsibilities.</p>
        </div>
        <a class="button" href="{{ route('coaches.create') }}">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-plus"></use></svg>
            Add Coach
        </a>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid coach-summary-grid">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
            <div class="medical-kpi-body"><small>Total Coaches</small><strong>{{ $summary['total'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-green"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span>
            <div class="medical-kpi-body"><small>Active Coaches</small><strong>{{ $summary['active'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
            <div class="medical-kpi-body"><small>Head Coaches</small><strong>{{ $summary['head'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
            <div class="medical-kpi-body"><small>Assigned Athletes</small><strong>{{ $summary['athletes'] }}</strong></div>
        </div>
    </div>

    <section class="card coach-filter-panel" aria-labelledby="find-coaches-heading">
        <div class="section-heading">
            <div>
                <h2 id="find-coaches-heading">Find Coaches</h2>
                <p class="meta">Search and filter coaching records.</p>
            </div>
            <span class="medical-count-chip" title="Total coaches on record" aria-label="{{ $summary['total'] }} coaches on record">{{ $summary['total'] }}</span>
        </div>

        <form method="GET" action="{{ route('coaches.index') }}" class="coach-filters">
            <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search coach..." aria-label="Search coach">
            <select class="form-control" name="sport_id" aria-label="Filter by sport">
                <option value="">All Sports</option>
                @foreach ($sports as $sport)
                    <option value="{{ $sport->id }}" @selected((string) request('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>
                @endforeach
            </select>
            <select class="form-control" name="coach_type" aria-label="Filter by coach type">
                <option value="">All Coach Types</option>
                @foreach ($coachTypes as $coachType)
                    <option value="{{ $coachType }}" @selected(request('coach_type') === $coachType)>{{ $coachType }}</option>
                @endforeach
            </select>
            <select class="form-control" name="status" aria-label="Filter by status">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="button" type="submit">Search</button>
            <a class="button button-secondary" href="{{ route('coaches.index') }}">Reset</a>
        </form>
    </section>

    <div class="card table-wrap coach-table-wrap">
        <div class="coach-list-heading">
            <h2 class="panel-title">Coach List</h2>
            @if ($coaches->total() > 0)
                <span class="coach-list-count" title="Coaches matching your filters" aria-label="{{ $coaches->total() }} coaches matching your filters">{{ $coaches->total() }}</span>
            @endif
        </div>

        <table class="data-table coach-table">
            <thead>
                <tr>
                    <th scope="col">Coach</th>
                    <th scope="col">Specialty</th>
                    <th scope="col">Sport</th>
                    <th scope="col">Coach Type</th>
                    <th scope="col" class="num">Assigned Athletes</th>
                    <th scope="col" class="num">Upcoming Events</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coaches as $coach)
                    @php
                        $coachInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($coach->name, 0, 2));
                    @endphp
                    <tr>
                        <td>
                            <span class="coach-cell">
                                <span class="avatar avatar--sm avatar-initials coach-avatar-sm" aria-hidden="true">{{ $coachInitials }}</span>
                                <span class="coach-cell-text">
                                    <strong>{{ $coach->name }}</strong>
                                    <small>{{ $coach->email }}</small>
                                    <small>{{ $coach->phone ?: 'No phone number' }}</small>
                                </span>
                            </span>
                        </td>
                        <td>{{ $coach->specialty }}</td>
                        <td>{{ $coach->sport?->name ?? 'Unassigned' }}</td>
                        <td>{{ $coach->coach_type ?: 'Not set' }}</td>
                        <td class="num">{{ $coach->sport?->athletes_count ?? 0 }}</td>
                        <td class="num">{{ $coach->events_count }}</td>
                        <td><span class="badge coach-status-{{ strtolower($coach->status) }}">{{ $coach->status }}</span></td>
                        <td>
                            <div class="coach-actions">
                                <a class="text-link" href="{{ route('coaches.show', $coach) }}">View</a>
                                <a class="text-link" href="{{ route('coaches.edit', $coach) }}">Edit</a>
                                <button class="row-action row-action-danger" type="button" data-confirm-dialog data-confirm-title="Delete Coach?" data-confirm-message="Are you sure you want to delete this coach? Coaches assigned to existing records will be set to inactive instead of deleted." data-confirm-label="Delete Coach" data-confirm-method="DELETE" data-confirm-url="{{ route('coaches.destroy', $coach) }}">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-cell">
                            @if ($hasFilters)
                                <strong>No coaches found</strong>
                                <br>
                                <small>There are currently no coaches matching your search or filter.</small>
                                <br>
                                <a class="button button-secondary" href="{{ route('coaches.index') }}">Reset Filters</a>
                            @else
                                <strong>No coaches found</strong>
                                <br>
                                <small>There are currently no coaches registered. Add a coach to get started.</small>
                                <br>
                                <a class="button" href="{{ route('coaches.create') }}">
                                    <svg class="button-icon" aria-hidden="true"><use href="#icon-plus"></use></svg>
                                    Add Coach
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($coaches->total() > 0)
            <div class="coach-pagination">
                <p>Showing {{ $coaches->firstItem() }}&ndash;{{ $coaches->lastItem() }} of {{ $coaches->total() }} coaches</p>
                <div class="coach-pagination-links">
                    @if ($coaches->onFirstPage())
                        <span class="coach-page-btn is-disabled">Previous</span>
                    @else
                        <a class="coach-page-btn" href="{{ $coaches->previousPageUrl() }}">Previous</a>
                    @endif
                    <span class="coach-page-status">Page {{ $coaches->currentPage() }} of {{ $coaches->lastPage() }}</span>
                    @if ($coaches->hasMorePages())
                        <a class="coach-page-btn" href="{{ $coaches->nextPageUrl() }}">Next</a>
                    @else
                        <span class="coach-page-btn is-disabled">Next</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection