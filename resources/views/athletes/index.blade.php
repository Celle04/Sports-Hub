@extends('layouts.portal')

@section('content')
    @php
        $hasFilters = request()->hasAny(['search', 'sport_id', 'grade', 'gender', 'status', 'eligibility']);
    @endphp

    <div class="module-header">
        <div>
            <h1>Athlete Management</h1>
            <p class="page-subtitle">Manage registered athletes and their sports participation</p>
        </div>
        <a class="button" href="{{ route('admin.applications', ['status' => 'Approved']) }}" title="Athlete accounts are created from approved applications">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-plus"></use></svg>
            Add Athlete
        </a>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid athlete-summary-grid">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
            <div class="medical-kpi-body"><small>Total Athletes</small><strong>{{ $summary['total'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-green"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span>
            <div class="medical-kpi-body"><small>Active Athletes</small><strong>{{ $summary['active'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
            <div class="medical-kpi-body"><small>Team Sport Athletes</small><strong>{{ $summary['team'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
            <div class="medical-kpi-body"><small>Individual Sport Athletes</small><strong>{{ $summary['individual'] }}</strong></div>
        </div>
    </div>

    <section class="card athlete-filter-panel" aria-labelledby="find-athletes-heading">
        <div class="section-heading">
            <div>
                <h2 id="find-athletes-heading">Find Athletes</h2>
                <p class="meta">Search and filter registered athlete accounts.</p>
            </div>
            <span class="medical-count-chip" title="Total athletes on record" aria-label="{{ $summary['total'] }} athletes on record">{{ $summary['total'] }}</span>
        </div>

        <form method="GET" action="{{ route('athletes.index') }}" class="athlete-filters">
            <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search athlete..." aria-label="Search athlete">
            <select class="form-control" name="sport_id" aria-label="Filter by sport">
                <option value="">All Sports</option>
                @foreach ($sports as $sport)
                    <option value="{{ $sport->id }}" @selected((string) request('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>
                @endforeach
            </select>
            <select class="form-control" name="grade" aria-label="Filter by grade">
                <option value="">All Grades</option>
                @foreach ($grades as $grade)
                    <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
            <select class="form-control" name="gender" aria-label="Filter by gender">
                <option value="">All Genders</option>
                @foreach ($genders as $gender)
                    <option value="{{ $gender }}" @selected(request('gender') === $gender)>{{ $gender }}</option>
                @endforeach
            </select>
            <select class="form-control" name="status" aria-label="Filter by status">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <select class="form-control" name="eligibility" aria-label="Filter by eligibility">
                <option value="">All Eligibility</option>
                @foreach ($eligibilities as $eligibility)
                    <option value="{{ $eligibility }}" @selected(request('eligibility') === $eligibility)>{{ $eligibility }}</option>
                @endforeach
            </select>
            <button class="button" type="submit">Search</button>
            <a class="button button-secondary" href="{{ route('athletes.index') }}">Reset</a>
        </form>
    </section>

    <div class="card table-wrap athlete-table-wrap">
        <div class="athlete-list-heading">
            <h2 class="panel-title">Athlete List</h2>
            @if (count($athletes) > 0)
                <span class="athlete-list-count" title="Athletes shown" aria-label="{{ count($athletes) }} athletes shown">{{ count($athletes) }}</span>
            @endif
        </div>

        <table class="data-table athlete-table">
            <thead>
                <tr>
                    <th scope="col">Athlete</th>
                    <th scope="col">Student ID</th>
                    <th scope="col">Grade</th>
                    <th scope="col">Sport</th>
                    <th scope="col">Coach</th>
                    <th scope="col">Eligibility</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($athletes as $athlete)
                    @php
                        $latestApplication = $athlete->applications->sortByDesc('created_at')->first();
                        $latestMedical = $athlete->medicalRecords->sortByDesc('examination_date')->first();
                        $eligibility = $latestMedical?->medical_status === 'Cleared' ? 'Eligible' : ($latestApplication?->status === 'Rejected' ? 'Not Eligible' : 'Pending');
                    @endphp
                    <tr>
                        <td>
                            <span class="athlete-cell">
                                <x-avatar :user="$athlete" size="sm" decorative class="athlete-avatar-sm" />
                                <span class="athlete-cell-text">
                                    <strong>{{ $athlete->name }}</strong>
                                    <small>{{ $athlete->username ?: $athlete->email }}</small>
                                </span>
                            </span>
                        </td>
                        <td>{{ $athlete->student_id ?: 'Not provided' }}</td>
                        <td>{{ $athlete->gradeLevel() ?: 'Not provided' }}</td>
                        <td>{{ $athlete->sport?->name ?? 'Unassigned' }}</td>
                        <td>{{ $athlete->sport?->coaches->pluck('name')->join(', ') ?: 'Unassigned' }}</td>
                        <td><span class="badge eligibility-{{ strtolower(str_replace(' ', '-', $eligibility)) }}">{{ $eligibility }}</span></td>
                        <td><span class="badge athlete-status-{{ strtolower($athlete->status) }}">{{ $athlete->status }}</span></td>
                        <td>
                            <div class="athlete-actions">
                                <a class="text-link" href="{{ route('athletes.show', $athlete) }}">View</a>
                                <a class="text-link" href="{{ route('athletes.edit', $athlete) }}">Edit</a>
                                <button class="row-action row-action-danger" type="button" data-confirm-dialog data-confirm-title="Delete Athlete?" data-confirm-message="Are you sure you want to delete this athlete? Athletes with related records will be set to inactive instead of deleted." data-confirm-label="Delete Athlete" data-confirm-method="DELETE" data-confirm-url="{{ route('athletes.destroy', $athlete) }}">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-cell">
                            @if ($hasFilters)
                                <strong>No athletes found</strong>
                                <br>
                                <small>Try adjusting your search or filters.</small>
                                <br>
                                <a class="button button-secondary" href="{{ route('athletes.index') }}">Reset Filters</a>
                            @else
                                <strong>No athletes registered yet.</strong>
                                <br>
                                <small>Athlete accounts are created from approved applications.</small>
                                <br>
                                <a class="button" href="{{ route('admin.applications', ['status' => 'Approved']) }}">
                                    <svg class="button-icon" aria-hidden="true"><use href="#icon-plus"></use></svg>
                                    Add Athlete
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection