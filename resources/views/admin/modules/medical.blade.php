@php
    $medicalSummary = $medicalSummary ?? [
        'total' => 0,
        'cleared' => 0,
        'pending' => 0,
        'restricted' => 0,
        'expiring_soon' => 0,
    ];
    $medicalFormMode = $medicalFormMode ?? 'create';
    $medicalFormRecord = $medicalFormRecord ?? null;
    $medicalStatuses = ['Pending', 'Cleared', 'Not Cleared', 'Restricted'];
@endphp

<div class="grid medical-summary-grid">
    <div class="card stat"><small>Total Records</small><strong>{{ $medicalSummary['total'] }}</strong></div>
    <div class="card stat"><small>Cleared</small><strong>{{ $medicalSummary['cleared'] }}</strong></div>
    <div class="card stat"><small>Pending</small><strong>{{ $medicalSummary['pending'] }}</strong></div>
    <div class="card stat"><small>Restricted</small><strong>{{ $medicalSummary['restricted'] }}</strong></div>
    <div class="card stat"><small>Expiring Soon</small><strong>{{ $medicalSummary['expiring_soon'] }}</strong></div>
</div>

@if (!empty($medicalAlerts))
    <section class="medical-alerts">
        @foreach ($medicalAlerts as $alert)
            <div class="alert-banner {{ $alert['type'] === 'danger' ? 'alert-danger' : 'alert-warning' }}">
                <strong>{{ strtoupper($alert['type']) }}</strong>
                <p>{{ $alert['message'] }}</p>
            </div>
        @endforeach
    </section>
@endif

<section class="card medical-form-card">
    <h2>{{ $medicalFormMode === 'edit' ? 'Edit Medical Record' : 'Add Medical Record' }}</h2>
    <form method="POST" action="{{ $medicalFormMode === 'edit' ? route('admin.medical.update', $medicalFormRecord) : route('admin.medical.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($medicalFormMode === 'edit') @method('PUT') @endif

        <div class="grid grid-3">
            <div class="form-group">
                <label>Athlete</label>
                <select class="form-control" name="athlete_id" required>
                    <option value="">Select athlete</option>
                    @foreach ($athletes ?? [] as $athlete)
                        <option value="{{ $athlete->id }}" @selected((string) old('athlete_id', $medicalFormRecord?->athlete_id ?? '') === (string) $athlete->id)>
                            {{ $athlete->name }} - {{ $athlete->sport?->name ?? 'Unassigned' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Examination Date</label>
                <input class="form-control" type="date" name="examination_date" value="{{ old('examination_date', $medicalFormRecord?->examination_date?->format('Y-m-d') ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Medical Status</label>
                <select class="form-control" name="medical_status">
                    @foreach ($medicalStatuses as $status)
                        <option value="{{ $status }}" @selected(old('medical_status', $medicalFormRecord?->medical_status ?? 'Pending') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-2" style="margin-top:12px">
            <div class="form-group">
                <label>Next Checkup Date</label>
                <input class="form-control" type="date" name="next_checkup_date" value="{{ old('next_checkup_date', $medicalFormRecord?->next_checkup_date?->format('Y-m-d') ?? '') }}">
            </div>
            <div class="form-group">
                <label>Medical Certificate</label>
                <input class="form-control" type="file" name="medical_certificate">
            </div>
        </div>

        <div class="grid grid-2" style="margin-top:12px">
            <div class="form-group">
                <label>Findings</label>
                <textarea class="form-control" name="findings" rows="3">{{ old('findings', $medicalFormRecord?->findings ?? '') }}</textarea>
            </div>
            <div class="form-group">
                <label>Restrictions</label>
                <textarea class="form-control" name="restrictions" rows="3">{{ old('restrictions', $medicalFormRecord?->restrictions ?? '') }}</textarea>
            </div>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" name="notes" rows="3">{{ old('notes', $medicalFormRecord?->notes ?? '') }}</textarea>
        </div>
        <button class="button" type="submit">{{ $medicalFormMode === 'edit' ? 'Update Record' : 'Save Record' }}</button>
    </form>
</section>

<section class="card medical-records-card">
    <h2>Medical Records</h2>
    <form method="GET" action="{{ route('admin.medical') }}" class="filters">
        <input class="form-control" name="search" value="{{ $search ?? '' }}" placeholder="Search athlete">
        <select class="form-control" name="sport_id"><option value="">All Sports</option>@foreach ($sports ?? [] as $sport)<option value="{{ $sport->id }}" @selected((string) ($sportFilter ?? '') === (string) $sport->id)>{{ $sport->name }}</option>@endforeach</select>
        <select class="form-control" name="status"><option value="">All Statuses</option>@foreach ($medicalStatuses as $status)<option value="{{ $status }}" @selected(($statusFilter ?? '') === $status)>{{ $status }}</option>@endforeach</select>
        <button class="button" type="submit">Search</button>
        <a class="button button-secondary" href="{{ route('admin.medical') }}">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="data-table medical-table">
            <thead><tr><th>Athlete</th><th>Sport</th><th>Last Checkup</th><th>Status</th><th>Next Checkup</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($medicalRecords ?? [] as $record)
                    <tr>
                        <td>{{ $record->athlete?->name }}</td>
                        <td>{{ $record->athlete?->sport?->name ?? 'Unassigned' }}</td>
                        <td>{{ $record->examination_date?->format('M j, Y') }}</td>
                        <td><span class="badge">{{ $record->medical_status }}</span></td>
                        <td>{{ $record->next_checkup_date?->format('M j, Y') ?? 'Not scheduled' }}</td>
                        <td><a class="text-link" href="{{ route('admin.medical.show', $record) }}">View</a> <a class="text-link" href="{{ route('admin.medical.edit', $record) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">No medical records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
