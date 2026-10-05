@extends('layouts.portal')

@section('content')

    <!-- Page Header -->
    <div class="module-header">
        <div>
            <h1>Applications Management</h1>
            <p class="page-subtitle">
                Review and process athlete applications
            </p>
        </div>
    </div>


    <!-- Success Message -->
    @if (session('success'))
        <div class="notice">
            {{ session('success') }}
        </div>
    @endif


    <!-- Error Message -->
    @if ($errors->any())
        <div class="notice notice-error">
            {{ $errors->first() }}
        </div>
    @endif


    @php
        $documentDefinitions =
            \App\Http\Controllers\ApplicationController::documentDefinitions();
    @endphp


    <!-- Application Summary -->
    <div class="grid application-summary-grid">

        <div class="card stat">
            <small>Total Applications</small>
            <strong>{{ $summary['Total'] }}</strong>
        </div>

        <div class="card stat">
            <small>Pending</small>
            <strong>{{ $summary['Pending'] }}</strong>
        </div>

        <div class="card stat">
            <small>Under Review</small>
            <strong>{{ $summary['Under Review'] }}</strong>
        </div>

        <div class="card stat">
            <small>Approved</small>
            <strong>{{ $summary['Approved'] }}</strong>
        </div>

        <div class="card stat">
            <small>Rejected</small>
            <strong>{{ $summary['Rejected'] }}</strong>
        </div>

        <div class="card stat">
            <small>Documents Required</small>
            <strong>{{ $summary['Documents Required'] }}</strong>
        </div>

    </div>


    <!-- Application Filters -->
    <section class="card application-filter-panel">

        <div class="section-heading">
            <div>
                <h2>Find Applications</h2>
                <p class="meta">
                    Search and filter submitted athlete applications.
                </p>
            </div>
        </div>


        <form
            method="GET"
            action="{{ route('admin.applications') }}"
            class="application-filters"
        >

            <!-- Search -->
            <input
                class="form-control"
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search applicant..."
                aria-label="Search applicant"
            >


            <!-- Sport -->
            <select
                class="form-control"
                name="sport_id"
            >
                <option value="">All Sports</option>

                @foreach ($sports as $sport)
                    <option
                        value="{{ $sport->id }}"
                        @selected((string) request('sport_id') === (string) $sport->id)
                    >
                        {{ $sport->name }}
                    </option>
                @endforeach
            </select>


            <!-- Grade -->
            <select
                class="form-control"
                name="grade"
            >
                <option value="">All Grades</option>

                @foreach ($grades as $grade)
                    <option
                        value="{{ $grade }}"
                        @selected(request('grade') === $grade)
                    >
                        {{ $grade }}
                    </option>
                @endforeach
            </select>


            <!-- Status -->
            <select
                class="form-control"
                name="status"
            >
                <option value="">All Statuses</option>

                @foreach ($statuses as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>


            <!-- Application Date -->
            <input
                class="form-control"
                type="date"
                name="date"
                value="{{ request('date') }}"
                aria-label="Filter by application date"
            >


            <!-- Search Button -->
            <button
                class="button"
                type="submit"
            >
                Search
            </button>


            <!-- Reset Button -->
            <a
                class="button button-muted"
                href="{{ route('admin.applications') }}"
            >
                Reset
            </a>

        </form>

    </section>


    <!-- Applications Table -->
    <div class="table-wrap application-table-wrap">

        <table class="data-table application-table">

            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Student ID</th>
                    <th>Sport</th>
                    <th>Applied</th>
                    <th>Eligibility Documents</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>


            <tbody>

                @forelse ($applications as $application)

                    <tr>

                        <!-- Applicant -->
                        <td>
                            <strong>
                                {{ $application->name }}
                            </strong>

                            <br>

                            <small>
                                {{ $application->email }}
                                &middot;
                                {{ $application->grade }}

                                @if ($application->gender)
                                    &middot;
                                    {{ $application->gender }}
                                @endif
                            </small>
                        </td>


                        <!-- Student ID -->
                        <td>
                            {{ $application->student_id ?: 'Not provided' }}
                        </td>


                        <!-- Sport -->
                        <td>
                            {{
                                $application->sportCategory?->name
                                ?? $application->sport
                                ?: 'Unassigned'
                            }}
                        </td>


                        <!-- Application Date -->
                        <td>
                            {{ $application->created_at?->format('M j, Y') }}
                        </td>


                        <!-- Eligibility Documents -->
                        <td>

                            <div class="document-status-list">

                                @foreach ($documentDefinitions as $key => $document)

                                    @php
                                        $documentStatus =
                                            $application->{$document['status']}
                                            ?: (
                                                $application->{$document['path']}
                                                    ? 'Submitted'
                                                    : 'Missing'
                                            );

                                        $documentLabel =
                                            $key === 'consent'
                                                ? 'Consent'
                                                : ucfirst($key);
                                    @endphp

                                    <span>

                                        <strong>
                                            {{ $documentLabel }}
                                        </strong>

                                        <em
                                            class="
                                                document-badge
                                                document-{{ strtolower($documentStatus) }}
                                            "
                                        >
                                            {{ $documentStatus }}
                                        </em>

                                    </span>

                                @endforeach

                            </div>

                        </td>


                        <!-- Status -->
                        <td>

                            <span
                                class="
                                    badge
                                    application-status-{{ strtolower(str_replace(' ', '-', $application->status)) }}
                                "
                            >
                                {{ $application->status }}
                            </span>

                        </td>


                        <!-- Actions -->
                        <td>

                            <div class="application-actions">

                                <a
                                    class="text-link"
                                    href="{{ route('applications.show', $application) }}"
                                >
                                    View
                                </a>


                                @if (
                                    $application->status === 'Approved'
                                    && !$application->athlete
                                )
                                    <a
                                        class="text-link"
                                        href="{{ route('athletes.create', $application) }}"
                                    >
                                        Create Account
                                    </a>
                                @endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <!-- Empty State -->
                    <tr>

                        <td
                            colspan="7"
                            class="empty-cell"
                        >

                            <strong>
                                {{
                                    request()->hasAny([
                                        'search',
                                        'sport_id',
                                        'grade',
                                        'status',
                                        'date'
                                    ])
                                        ? 'No applications match your search.'
                                        : 'No athlete applications found.'
                                }}
                            </strong>

                            <br>

                            <small>
                                Applications submitted by athletes
                                will appear here.
                            </small>


                            @if (
                                request()->hasAny([
                                    'search',
                                    'sport_id',
                                    'grade',
                                    'status',
                                    'date'
                                ])
                            )

                                <br>

                                <a
                                    class="button button-muted"
                                    href="{{ route('admin.applications') }}"
                                >
                                    Reset Filters
                                </a>

                            @endif

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

@endsection