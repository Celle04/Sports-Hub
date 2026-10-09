@extends('layouts.portal')

@section('content')
    @php
        $appController = \App\Http\Controllers\ApplicationController::class;
        $documentDefinitions = $appController::documentDefinitions();
        $statusTiles = [
            'Pending' => ['icon' => 'bell', 'tone' => 'tone-gold'],
            'Under Review' => ['icon' => 'eye', 'tone' => 'tone-blue'],
            'Documents Required' => ['icon' => 'certificate', 'tone' => 'tone-amber'],
            'Approved' => ['icon' => 'trophy', 'tone' => 'tone-green'],
            'Rejected' => ['icon' => 'trash', 'tone' => 'tone-danger'],
            'Waitlisted' => ['icon' => 'users', 'tone' => 'tone-violet'],
        ];
        $initials = static function (string $name): string {
            $pieces = preg_split('/\s+/', trim($name)) ?: [];

            return mb_strtoupper(implode('', array_map(
                static fn (string $word): string => mb_substr($word, 0, 1),
                array_slice($pieces, 0, 2),
            )));
        };
        $documentFlag = static function (string $documentStatus): array {
            return match ($documentStatus) {
                'Verified' => ['text' => '✓ Verified', 'tone' => 'is-verified'],
                'Submitted' => ['text' => '✓ Submitted', 'tone' => 'is-submitted'],
                'Pending' => ['text' => '⏳ Pending', 'tone' => 'is-pending'],
                'Rejected' => ['text' => '! Rejected', 'tone' => 'is-missing'],
                default => ['text' => '! Missing', 'tone' => 'is-missing'],
            };
        };
        $filtering = request()->hasAny(['search', 'sport_id', 'grade', 'status', 'date']);
        $emptyIcon = $filtering ? 'search' : 'clipboard';
    @endphp

    <div class="module-header">
        <div><h1>Applications Management</h1><p class="page-subtitle">Review and process athlete applications</p></div>
    </div>

    @if (session('success'))<div class="notice">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif

    <div class="grid application-summary-grid">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
            <div class="medical-kpi-body"><small>Total Applications</small><strong>{{ $summary['Total'] ?? 0 }}</strong></div>
        </div>
        @foreach ($appController::statuses() as $tileStatus)
            @php
                $tile = $statusTiles[$tileStatus] ?? ['icon' => 'clipboard', 'tone' => 'tone-red'];
            @endphp
            <div class="card stat medical-kpi">
                <span class="medical-kpi-icon {{ $tile['tone'] }}"><svg aria-hidden="true"><use href="#icon-{{ $tile['icon'] }}"></use></svg></span>
                <div class="medical-kpi-body"><small>{{ $tileStatus }}</small><strong>{{ $summary[$tileStatus] ?? 0 }}</strong></div>
            </div>
        @endforeach
    </div>

    <section class="card application-filter-panel">
        <div class="section-heading">
            <div>
                <h2>Find Applications</h2>
                <p class="meta">Search and filter submitted athlete applications.</p>
            </div>
            <span class="medical-count-chip" title="Total applications on record" aria-label="{{ $summary['Total'] ?? 0 }} applications on record">{{ $summary['Total'] ?? 0 }}</span>
        </div>
        <form method="GET" action="{{ route('admin.applications') }}" class="application-filters">
            <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search applicant..." aria-label="Search applicant">
            <select class="form-control" name="sport_id" aria-label="Filter by sport">
                <option value="">All Sports</option>
                @foreach ($sports as $sport)<option value="{{ $sport->id }}" @selected((string) request('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>@endforeach
            </select>
            <select class="form-control" name="grade" aria-label="Filter by grade">
                <option value="">All Grades</option>
                @foreach ($grades as $grade)<option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>@endforeach
            </select>
            <select class="form-control" name="status" aria-label="Filter by application status">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach
            </select>
            <input class="form-control" type="date" name="date" value="{{ request('date') }}" aria-label="Filter by application date" title="Application date">
            <button class="button" type="submit">Search</button>
            <a class="button button-secondary" href="{{ route('admin.applications') }}">Reset</a>
        </form>
    </section>

    <div class="card table-wrap application-table-wrap">
        <h2 class="panel-title">Applications List</h2>
        <table class="data-table application-table">
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Student ID</th>
                    <th scope="col">Sport</th>
                    <th scope="col">Applied</th>
                    <th scope="col">Eligibility Documents</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $application)
                    @php
                        $applicantMeta = collect([
                            filled($application->grade) ? e($application->grade) : null,
                            $application->gender ? e($application->gender) : null,
                        ])->filter()->implode(' &middot; ');
                    @endphp
                    <tr>
                        <td>
                            <div class="application-applicant">
                                <span class="application-avatar" aria-hidden="true">{{ filled($application->name ?? '') ? $initials($application->name) : '?' }}</span>
                                <div class="application-applicant-copy">
                                    <strong>{{ $application->name }}</strong>
                                    <small>{{ $application->email }}</small>
                                    @if ($applicantMeta)<small>{!! $applicantMeta !!}</small>@endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $application->student_id ?: 'Not provided' }}</td>
                        <td>{{ $application->sportCategory?->name ?? $application->sport ?: 'Unassigned' }}</td>
                        <td>{{ $application->created_at?->format('M j, Y') }}</td>
                        <td>
                            <div class="document-status-list">
                                @foreach ($documentDefinitions as $key => $document)
                                    @php
                                        $documentStatus = $application->{$document['path']}
                                            ? ($application->{$document['status']} ?: 'Submitted')
                                            : 'Missing';
                                        $flag = $documentFlag($documentStatus);
                                    @endphp
                                    <span>
                                        <strong>{{ $document['label'] }}</strong>
                                        <em class="application-doc-flag {{ $flag['tone'] }}">{{ $flag['text'] }}</em>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td><span class="badge application-status-{{ strtolower(str_replace(' ', '-', $application->status)) }}">{{ $application->status }}</span></td>
                        <td>
                            <div class="application-actions">
                                <a class="button" href="{{ route('applications.show', $application) }}" aria-label="View application from {{ $application->name }}">View</a>
                                @if ($application->status === 'Approved' && ! $application->athlete)
                                    <a class="row-action" href="{{ route('athletes.create', $application) }}">Create Account</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-cell">
                            <div class="application-empty-cell">
                                <span class="application-empty-icon"><svg aria-hidden="true"><use href="#icon-{{ $emptyIcon }}"></use></svg></span>
                                <strong>No applications found</strong>
                                <small>{{ $filtering ? 'Try adjusting your filters or search criteria.' : 'Applications submitted by athletes will appear here.' }}</small>
                                @if ($filtering)
                                    <a class="button button-secondary" href="{{ route('admin.applications') }}">Reset Filters</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
