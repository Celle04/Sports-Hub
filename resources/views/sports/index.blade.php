@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Sports Management</h1>
            <p class="page-subtitle">Manage sports categories and classifications</p>
        </div>
        <a class="button" href="{{ route('sports.create') }}">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-plus"></use></svg>
            Add Sport
        </a>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid sports-summary-grid">
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-trophy"></use></svg></span>
            <div class="medical-kpi-body"><small>Total Sports</small><strong>{{ $summary['total'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-blue"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
            <div class="medical-kpi-body"><small>Team Sports</small><strong>{{ $summary['team'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
            <div class="medical-kpi-body"><small>Individual Sports</small><strong>{{ $summary['individual'] }}</strong></div>
        </div>
        <div class="card stat medical-kpi">
            <span class="medical-kpi-icon tone-green"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span>
            <div class="medical-kpi-body"><small>Active Sports</small><strong>{{ $summary['active'] }}</strong></div>
        </div>
    </div>

    <section class="card sports-filter-panel" aria-labelledby="find-sports-heading">
        <div class="section-heading">
            <div>
                <h2 id="find-sports-heading">Find Sports</h2>
                <p class="meta">Search and filter the sports catalogue.</p>
            </div>
            <span class="medical-count-chip" title="Total sports on record" aria-label="{{ $summary['total'] }} sports on record">{{ $summary['total'] }}</span>
        </div>

        <form method="GET" action="{{ route('sports.index') }}" class="sports-filters">
            <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search sport..." aria-label="Search sport">
            <select class="form-control" name="classification" aria-label="Filter by classification">
                <option value="">All Classifications</option>
                @foreach ($classifications as $classification)
                    <option value="{{ $classification }}" @selected(request('classification') === $classification)>{{ $classification }}</option>
                @endforeach
            </select>
            <select class="form-control" name="status" aria-label="Filter by status">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="button" type="submit">Search</button>
            <a class="button button-secondary" href="{{ route('sports.index') }}">Reset</a>
        </form>
    </section>

    <div class="card table-wrap sports-table-wrap">
        <div class="sports-list-heading">
            <h2 class="panel-title">Sports List</h2>
            @if (count($sports) > 0)
                <span class="sport-list-count">{{ count($sports) }}</span>
            @endif
        </div>

        <table class="data-table sports-table">
            <thead>
                <tr>
                    <th scope="col">Sport</th>
                    <th scope="col">Classification</th>
                    <th scope="col">Description</th>
                    <th scope="col" class="num">Athletes</th>
                    <th scope="col" class="num">Coaches</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sports as $sport)
                    <tr>
                        <td class="cell-sport"><strong>{{ $sport->name }}</strong></td>
                        <td>{{ $sport->classification }}</td>
                        <td class="cell-description"><span title="{{ $sport->description }}">{{ $sport->description }}</span></td>
                        <td class="num">{{ $sport->athletes_count }}</td>
                        <td class="num">{{ $sport->coaches_count }}</td>
                        <td>
                            <span class="badge sport-status-{{ strtolower($sport->status) }}">{{ $sport->status }}</span>
                        </td>
                        <td>
                            <div class="sports-actions">
                                <a class="text-link" href="{{ route('sports.show', $sport) }}">View</a>
                                <a class="text-link" href="{{ route('sports.edit', $sport) }}">Edit</a>
                                <button class="row-action row-action-danger" type="button" data-confirm-dialog data-confirm-title="Delete Sport?" data-confirm-message="Are you sure you want to delete this sport?" data-confirm-label="Delete Sport" data-confirm-method="DELETE" data-confirm-url="{{ route('sports.destroy', $sport) }}">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-cell">
                            @if (request()->hasAny(['search', 'classification', 'status']))
                                <strong>No sports found</strong>
                                <br>
                                <small>Try adjusting your search or filters.</small>
                                <br>
                                <a class="button button-secondary" href="{{ route('sports.index') }}">Reset Filters</a>
                            @else
                                <strong>No sports have been added yet.</strong>
                                <br>
                                <small>Add your first sport to get started.</small>
                                <br>
                                <a class="button" href="{{ route('sports.create') }}">
                                    <svg class="button-icon" aria-hidden="true"><use href="#icon-plus"></use></svg>
                                    Add Sport
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection