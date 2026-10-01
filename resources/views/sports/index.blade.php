@extends('layouts.portal')

@section('content')

    <div class="module-header">
        <div>
            <h1>Sports Management</h1>
            <p class="page-subtitle">
                Manage sports categories and classifications
            </p>
        </div>

        <a class="button" href="{{ route('sports.create') }}">
            Add Sport
        </a>
    </div>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="notice">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation / Error Message --}}
    @if ($errors->any())
        <div class="notice notice-error">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Sports Summary --}}
    <div class="grid sports-summary-grid">
        <div class="card stat">
            <small>Total Sports</small>
            <strong>{{ $summary['total'] }}</strong>
        </div>

        <div class="card stat">
            <small>Team Sports</small>
            <strong>{{ $summary['team'] }}</strong>
        </div>

        <div class="card stat">
            <small>Individual Sports</small>
            <strong>{{ $summary['individual'] }}</strong>
        </div>

        <div class="card stat">
            <small>Active Sports</small>
            <strong>{{ $summary['active'] }}</strong>
        </div>
    </div>

    {{-- Search and Filters --}}
    <section class="card sports-filter-panel">
        <div class="section-heading">
            <div>
                <h2>Find Sports</h2>
                <p class="meta">
                    Search and filter the sports catalogue.
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('sports.index') }}"
            class="sports-filters"
        >
            {{-- Search --}}
            <input
                class="form-control"
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search sport..."
                aria-label="Search sport"
            >

            {{-- Classification --}}
            <select class="form-control" name="classification">
                <option value="">All Classifications</option>

                @foreach ($classifications as $classification)
                    <option
                        value="{{ $classification }}"
                        @selected(request('classification') === $classification)
                    >
                        {{ $classification }}
                    </option>
                @endforeach
            </select>

            {{-- Status --}}
            <select class="form-control" name="status">
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

            <button class="button" type="submit">
                Search
            </button>

            <a
                class="button button-muted"
                href="{{ route('sports.index') }}"
            >
                Reset
            </a>
        </form>
    </section>

    {{-- Sports Table --}}
    <div class="card table-wrap sports-table-wrap">

        <h2 class="panel-title">
            Sports List
        </h2>

        <table class="data-table sports-table">

            <thead>
                <tr>
                    <th>Sport Name</th>
                    <th>Classification</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Athletes</th>
                    <th>Coaches</th>
                    <th>Events</th>
                    <th>Applications</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse ($sports as $sport)

                    <tr>

                        {{-- Sport Name --}}
                        <td>
                            <strong>{{ $sport->name }}</strong>
                        </td>

                        {{-- Classification --}}
                        <td>
                            {{ $sport->classification }}
                        </td>

                        {{-- Description --}}
                        <td>
                            {{ $sport->description }}
                        </td>

                        {{-- Status --}}
                        <td>
                            <span
                                class="badge sport-status-{{ strtolower($sport->status) }}"
                            >
                                {{ $sport->status }}
                            </span>
                        </td>

                        {{-- Athletes --}}
                        <td>
                            {{ $sport->athletes_count }}
                        </td>

                        {{-- Coaches --}}
                        <td>
                            {{ $sport->coaches_count }}
                        </td>

                        {{-- Events --}}
                        <td>
                            {{ $sport->events_count }}
                        </td>

                        {{-- Applications --}}
                        <td>
                            {{ $sport->applications_count }}
                        </td>

                        {{-- Actions --}}
                        <td>
                            <div class="sports-actions">

                                <a
                                    class="text-link"
                                    href="{{ route('sports.show', $sport) }}"
                                >
                                    View
                                </a>

                                <a
                                    class="text-link"
                                    href="{{ route('sports.edit', $sport) }}"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('sports.destroy', $sport) }}"
                                    onsubmit="return confirm('Are you sure you want to delete this sport?');"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        class="text-link text-button"
                                        type="submit"
                                    >
                                        Delete
                                    </button>
                                </form>

                            </div>
                        </td>

                    </tr>

                @empty

                    {{-- Empty State --}}
                    <tr>
                        <td colspan="9" class="empty-cell">

                            <strong>
                                {{
                                    request()->hasAny([
                                        'search',
                                        'classification',
                                        'status'
                                    ])
                                        ? 'No sports match your search.'
                                        : 'No sports registered yet.'
                                }}
                            </strong>

                            <br>

                            <small>
                                Add a sport to begin managing sports categories.
                            </small>

                            @if (request()->hasAny([
                                'search',
                                'classification',
                                'status'
                            ]))

                                <br>

                                <a
                                    class="button button-muted"
                                    href="{{ route('sports.index') }}"
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