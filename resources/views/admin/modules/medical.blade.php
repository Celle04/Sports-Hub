@php
    $medicalSummary = $medicalSummary ?? [
        'total' => 0,
        'cleared' => 0,
        'pending' => 0,
        'restricted' => 0,
        'expiring_soon' => 0,
        'under_recovery' => 0,
    ];
    $medicalFormMode = $medicalFormMode ?? 'create';
    $medicalFormRecord = $medicalFormRecord ?? null;
    $medicalStatuses = $medicalStatuses ?? ['Pending', 'Cleared', 'Restricted', 'Not Cleared'];
    $medicalCertificateStates = $medicalCertificateStates ?? ['Valid', 'Expiring Soon', 'Expired', 'Missing'];
    $medicalSeverities = $medicalSeverities ?? ['Mild', 'Moderate', 'Severe'];
    $medicalClearances = $medicalClearances ?? ['Pending', 'Cleared', 'Not Cleared'];
    $medicalViewMode = $medicalViewMode ?? 'index';
    $selectedMedicalRecord = $selectedMedicalRecord ?? null;
    $medicalIncidents = $medicalIncidents ?? collect();
    $medicalIncidentFormMode = $medicalIncidentFormMode ?? 'create';
    $medicalIncidentFormRecord = $medicalIncidentFormRecord ?? null;
    $medicalProfileDocuments = $medicalProfileDocuments ?? collect();
    $expiringCertificates = $expiringCertificates ?? collect();
    $upcomingCheckups = $upcomingCheckups ?? collect();
    $overdueCheckups = $overdueCheckups ?? collect();
    $medicalExpiringDays = $medicalExpiringDays ?? 30;
    $recordCount = count($medicalRecords ?? []);

    $initials = static function (string $name): string {
        $pieces = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(implode('', array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($pieces, 0, 2),
        )));
    };
@endphp

<div class="grid medical-summary-grid">
    <div class="card stat medical-kpi">
        <span class="medical-kpi-icon tone-red"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
        <div class="medical-kpi-body"><small>Total Records</small><strong>{{ $medicalSummary['total'] }}</strong></div>
    </div>
    <div class="card stat medical-kpi">
        <span class="medical-kpi-icon tone-green"><svg aria-hidden="true"><use href="#icon-medical"></use></svg></span>
        <div class="medical-kpi-body"><small>Cleared</small><strong>{{ $medicalSummary['cleared'] }}</strong></div>
    </div>
    <div class="card stat medical-kpi">
        <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
        <div class="medical-kpi-body"><small>Pending</small><strong>{{ $medicalSummary['pending'] }}</strong></div>
    </div>
    <div class="card stat medical-kpi">
        <span class="medical-kpi-icon tone-violet"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
        <div class="medical-kpi-body"><small>Restricted</small><strong>{{ $medicalSummary['restricted'] }}</strong></div>
    </div>
    <div class="card stat medical-kpi">
        <span class="medical-kpi-icon tone-amber"><svg aria-hidden="true"><use href="#icon-certificate"></use></svg></span>
        <div class="medical-kpi-body"><small>Expiring Soon</small><strong>{{ $medicalSummary['expiring_soon'] }}</strong></div>
    </div>
    <div class="card stat medical-kpi">
        <span class="medical-kpi-icon tone-danger"><svg aria-hidden="true"><use href="#icon-activity"></use></svg></span>
        <div class="medical-kpi-body"><small>Under Recovery</small><strong>{{ $medicalSummary['under_recovery'] }}</strong></div>
    </div>
</div>

@if (!empty($medicalAlerts))
    <section class="medical-alerts">
        @foreach ($medicalAlerts as $alert)
            <div class="alert-banner {{ $alert['type'] === 'danger' ? 'alert-danger' : 'alert-warning' }}">
                <strong>{{ $alert['title'] }}</strong>
                <p>{{ $alert['message'] }}</p>
            </div>
        @endforeach
    </section>
@endif

@if ($medicalViewMode === 'show' && $selectedMedicalRecord)
    <section class="card medical-detail-card">
        <div class="profile-hero medical-hero">
            <span class="avatar avatar--lg avatar-initials medical-hero-avatar" aria-hidden="true">{{ filled($selectedMedicalRecord->athlete?->name ?? '') ? $initials($selectedMedicalRecord->athlete->name) : '?' }}</span>
            <div class="medical-hero-copy">
                <span class="admin-dashboard-kicker">ATHLETE MEDICAL PROFILE</span>
                <h2>{{ $selectedMedicalRecord->athlete?->name ?? 'Unknown athlete' }}</h2>
                <p>
                    {{ $selectedMedicalRecord->sportName() }}
                    @if ($selectedMedicalRecord->athlete?->student_id) &middot; {{ $selectedMedicalRecord->athlete->student_id }} @endif
                    &middot; Last examined {{ $selectedMedicalRecord->examinationDateLabel() }}
                    @if ($selectedMedicalRecord->isArchived()) &middot; <span class="badge">Archived</span> @endif
                </p>
            </div>
            <a class="medical-hero-back" href="{{ route('admin.medical') }}">Back to list</a>
        </div>

        <div class="medical-fact-grid">
            <div class="medical-fact"><span>Medical Status</span><strong><span class="{{ $selectedMedicalRecord->statusBadgeClass() }}">{{ $selectedMedicalRecord->medical_status }}</span></strong></div>
            <div class="medical-fact"><span>Examination Date</span><strong>{{ $selectedMedicalRecord->examinationDateLabel() }}</strong></div>
            <div class="medical-fact"><span>Next Checkup</span><strong>{{ $selectedMedicalRecord->nextCheckupLabel() }}</strong></div>
            <div class="medical-fact"><span>Certificate Status</span><strong><span class="{{ $selectedMedicalRecord->certificateBadgeClass() }}">{{ $selectedMedicalRecord->certificateState() }}</span></strong></div>
            <div class="medical-fact">
                <span>Days Until Expiry</span>
                <strong>{{ $selectedMedicalRecord->daysUntilExpiry() === null ? 'No deadline set' : ($selectedMedicalRecord->daysUntilExpiry() < 0 ? abs($selectedMedicalRecord->daysUntilExpiry()).' day(s) overdue' : $selectedMedicalRecord->daysUntilExpiry().' day(s) left') }}</strong>
            </div>
            <div class="medical-fact"><span>Record State</span><strong>{{ $selectedMedicalRecord->isArchived() ? 'Archived' : 'Active' }}</strong></div>
        </div>

        @can('view-medical-details')
            <div class="medical-copy-grid">
                <div class="medical-copy-card">
                    <strong>Findings</strong>
                    <p>{{ filled($selectedMedicalRecord->findings) ? $selectedMedicalRecord->findings : 'No findings recorded for this examination.' }}</p>
                </div>
                <div class="medical-copy-card">
                    <strong>Restrictions</strong>
                    <p>{{ filled($selectedMedicalRecord->restrictions) ? $selectedMedicalRecord->restrictions : 'No participation restrictions recorded.' }}</p>
                </div>
                <div class="medical-copy-card">
                    <strong>Notes</strong>
                    <p>{{ filled($selectedMedicalRecord->notes) ? $selectedMedicalRecord->notes : 'No additional notes recorded.' }}</p>
                </div>
            </div>

            <div class="medical-documents">
                <div>
                    <strong>Medical Documents</strong>
                    @if (filled($selectedMedicalRecord->medical_certificate))
                        <p>
                            <a class="text-link" href="{{ route('admin.medical.certificate.view', $selectedMedicalRecord) }}">View certificate</a>
                            &middot;
                            <a class="text-link" href="{{ route('admin.medical.certificate', $selectedMedicalRecord) }}">Download certificate</a>
                        </p>
                    @else
                        <p>No medical certificate has been uploaded for this record.</p>
                    @endif
                </div>

                @if ($medicalProfileDocuments->isNotEmpty())
                    <div class="medical-documents-filed">
                        <small class="meta">Documents filed with this athlete's applications:</small>
                        <p>
                            @foreach ($medicalProfileDocuments as $document)
                                <a class="text-link" href="{{ route('applications.documents.download', [$document, 'medical']) }}">{{ 'Application #'.$document->id }} &middot; {{ $document->created_at?->format('M j, Y') }}</a>{{ $loop->last ? '' : ' &middot; ' }}
                            @endforeach
                        </p>
                    </div>
                @endif
            </div>

            <div class="medical-history">
                <div class="section-heading">
                    <div>
                        <span class="admin-dashboard-kicker">CASE HISTORY</span>
                        <h2>Injury &amp; Medical Incident History</h2>
                    </div>
                </div>
                @if ($selectedMedicalRecord->injuries->isEmpty())
                    <p class="empty-state">No injuries or medical incidents recorded.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table medical-incidents-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sport</th>
                                    <th>Activity</th>
                                    <th>Injury</th>
                                    <th>Severity</th>
                                    <th>Treatment / Rest</th>
                                    <th>Clearance</th>
                                    <th>Return to Play</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($selectedMedicalRecord->injuries as $incident)
                                    <tr>
                                        <td>{{ $incident->incidentDateLabel() }}</td>
                                        <td>{{ $incident->sportName() }}</td>
                                        <td>{{ $incident->activity ?: 'Not recorded' }}</td>
                                        <td>
                                            <strong>{{ $incident->injury_type }}</strong>
                                            @if ($incident->body_part)<br><small class="meta">{{ $incident->body_part }}</small>@endif
                                        </td>
                                        <td><span class="{{ $incident->severityBadgeClass() }}">{{ $incident->severity }}</span></td>
                                        <td>
                                            {{ $incident->restPeriodLabel() }}
                                            <br><small class="meta">{{ $incident->treatment ?: 'No treatment recorded' }}</small>
                                        </td>
                                        <td><span class="{{ $incident->clearanceBadgeClass() }}">{{ $incident->medical_clearance }}</span></td>
                                        <td>{{ $incident->returnToPlayLabel() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcan

        <div class="medical-detail-actions">
            <a class="button" href="{{ route('admin.medical.edit', $selectedMedicalRecord) }}">Edit Record</a>
            @if ($selectedMedicalRecord->isArchived())
                <button class="button button-secondary" type="button" data-confirm-dialog data-confirm-title="Restore Medical Record?" data-confirm-message="Restore this medical record to the active list?" data-confirm-label="Restore" data-confirm-icon="check" data-confirm-variant="success" data-confirm-method="PATCH" data-confirm-url="{{ route('admin.medical.restore', $selectedMedicalRecord) }}">Restore</button>
            @else
                <button class="button button-secondary" type="button" data-confirm-dialog data-confirm-title="Archive Medical Record?" data-confirm-message="Archive this medical record? It will be removed from the active list." data-confirm-label="Archive" data-confirm-icon="warning" data-confirm-variant="warning" data-confirm-method="PATCH" data-confirm-url="{{ route('admin.medical.archive', $selectedMedicalRecord) }}">Archive</button>
            @endif
        </div>
    </section>
@else
    <section class="card medical-form-card" id="medical-record-form">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">{{ $medicalFormMode === 'edit' ? 'UPDATE RECORD' : 'NEW RECORD' }}</span>
                <h2>{{ $medicalFormMode === 'edit' ? 'Edit Medical Record' : 'Add Medical Record' }}</h2>
                <p>Record an athlete's examination, clearance status and next checkup. Expiration warnings are calculated from the next checkup date.</p>
            </div>
            @if ($medicalFormMode === 'edit')
                <a class="text-link" href="{{ route('admin.medical') }}">Cancel</a>
            @endif
        </div>

        <form method="POST" action="{{ $medicalFormMode === 'edit' ? route('admin.medical.update', $medicalFormRecord) : route('admin.medical.store') }}" enctype="multipart/form-data" class="medical-form-body">
            @csrf
            @if ($medicalFormMode === 'edit') @method('PUT') @endif

            <div class="grid grid-3">
                <div class="form-group">
                    <label for="medical-athlete">Athlete</label>
                    <select class="form-control @error('athlete_id') is-invalid @enderror" id="medical-athlete" name="athlete_id" required>
                        <option value="">Select athlete</option>
                        @foreach ($athletes ?? [] as $option)
                            <option value="{{ $option->id }}" @selected((string) old('athlete_id', $medicalFormRecord?->athlete_id ?? '') === (string) $option->id)>
                                {{ $option->name }}@if ($option->student_id) ({{ $option->student_id }})@endif &mdash; {{ $option->sport?->name ?? 'Unassigned' }}
                            </option>
                        @endforeach
                    </select>
                    @error('athlete_id')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="medical-examination-date">Examination Date</label>
                    <input class="form-control @error('examination_date') is-invalid @enderror" id="medical-examination-date" type="date" name="examination_date" value="{{ old('examination_date', $medicalFormRecord?->examination_date?->format('Y-m-d') ?? '') }}" required>
                    @error('examination_date')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="medical-status">Medical Status</label>
                    <select class="form-control @error('medical_status') is-invalid @enderror" id="medical-status" name="medical_status" required>
                        @foreach ($medicalStatuses as $status)
                            <option value="{{ $status }}" @selected(old('medical_status', $medicalFormRecord?->medical_status ?? 'Pending') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <small class="form-hint">Cleared, Pending, Restricted or Not Cleared.</small>
                    @error('medical_status')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="medical-next-checkup">Next Checkup Date</label>
                    <input class="form-control @error('next_checkup_date') is-invalid @enderror" id="medical-next-checkup" type="date" name="next_checkup_date" value="{{ old('next_checkup_date', $medicalFormRecord?->next_checkup_date?->format('Y-m-d') ?? '') }}">
                    <small class="form-hint">Doubles as the certificate expiration date. Records due within {{ $medicalExpiringDays }} days are flagged as expiring soon.</small>
                    @error('next_checkup_date')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="medical-certificate">Medical Certificate</label>
                    <input class="form-control @error('medical_certificate') is-invalid @enderror" id="medical-certificate" type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                    <small class="form-hint">PDF, JPG, JPEG or PNG &middot; maximum {{ round((int) config('medical.certificate_max_kb', 2048) / 1024) }} MB.@if (filled($medicalFormRecord?->medical_certificate)) A certificate is already attached and will be replaced.@endif</small>
                    @error('medical_certificate')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            @can('view-medical-details')
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="medical-findings">Findings</label>
                        <textarea class="form-control @error('findings') is-invalid @enderror" id="medical-findings" name="findings" rows="3" maxlength="1000" placeholder="Examination findings">{{ old('findings', $medicalFormRecord?->findings ?? '') }}</textarea>
                        @error('findings')<small class="form-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-group">
                        <label for="medical-restrictions">Restrictions</label>
                        <textarea class="form-control @error('restrictions') is-invalid @enderror" id="medical-restrictions" name="restrictions" rows="3" maxlength="1000" placeholder="Participation restrictions">{{ old('restrictions', $medicalFormRecord?->restrictions ?? '') }}</textarea>
                        @error('restrictions')<small class="form-error">{{ $message }}</small>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="medical-notes">Notes</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="medical-notes" name="notes" rows="3" maxlength="2000" placeholder="Additional notes">{{ old('notes', $medicalFormRecord?->notes ?? '') }}</textarea>
                    @error('notes')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            @endcan

            <div class="medical-form-actions">
                <button class="button" type="submit">{{ $medicalFormMode === 'edit' ? 'Update Record' : 'Save Record' }}</button>
                @if ($medicalFormMode === 'edit')
                    <a class="button button-secondary" href="{{ route('admin.medical') }}">Cancel</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card medical-records-card">
        <div class="medical-card-head">
            <div>
                <span class="admin-dashboard-kicker">ALL RECORDS</span>
                <h2>Medical Records</h2>
                <p>{{ $recordCount }} record{{ $recordCount === 1 ? '' : 's' }} matching the current filters</p>
            </div>
            <span class="medical-count-chip">{{ $recordCount }}</span>
        </div>

        <form method="GET" action="{{ route('admin.medical') }}" class="filters medical-filters">
            <input class="form-control medical-search" name="search" value="{{ $search ?? '' }}" placeholder="Search athlete..." aria-label="Search athlete">
            <select class="form-control" name="sport_id" aria-label="Filter by sport">
                <option value="">All Sports</option>
                @foreach ($sports ?? [] as $option)
                    <option value="{{ $option->id }}" @selected((string) ($sportFilter ?? '') === (string) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
            <select class="form-control" name="status" aria-label="Filter by medical status">
                <option value="">All Statuses</option>
                @foreach ($medicalStatuses as $status)
                    <option value="{{ $status }}" @selected(($statusFilter ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <select class="form-control" name="certificate" aria-label="Filter by certificate status">
                <option value="">All Certificates</option>
                @foreach ($medicalCertificateStates as $state)
                    <option value="{{ $state }}" @selected(($certificateFilter ?? '') === $state)>{{ $state }}</option>
                @endforeach
            </select>
            <input class="form-control" name="examination_date" type="date" value="{{ $examinationDateFilter ?? '' }}" aria-label="Examination date" title="Examination date">
            <label class="medical-filter-toggle">
                <input type="checkbox" name="archived" value="1" @checked($archivedFilter ?? false)>
                Show archived only
            </label>
            <button class="button" type="submit">Search</button>
            <a class="button button-secondary" href="{{ route('admin.medical') }}">Reset</a>
        </form>

        <div class="table-wrap">
            <table class="data-table medical-table">
                <thead>
                    <tr>
                        <th>Athlete</th>
                        <th>Sport</th>
                        <th>Examination</th>
                        <th>Status</th>
                        <th>Next Checkup</th>
                        <th>Certificate</th>
                        <th>Record State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($medicalRecords ?? [] as $record)
                        <tr>
                            <td>
                                <div class="medical-cell">
                                    <span class="avatar avatar-initials medical-avatar" aria-hidden="true">{{ filled($record->athlete?->name ?? '') ? $initials($record->athlete->name) : '?' }}</span>
                                    <div>
                                        <strong>{{ $record->athlete?->name ?? 'Unknown athlete' }}</strong>
                                        @if ($record->athlete?->student_id)<br><small class="meta">{{ $record->athlete->student_id }}</small>@endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $record->sportName() }}</td>
                            <td>{{ $record->examinationDateLabel() }}</td>
                            <td><span class="{{ $record->statusBadgeClass() }}">{{ $record->medical_status }}</span></td>
                            <td>{{ $record->nextCheckupLabel() }}</td>
                            <td><span class="{{ $record->certificateBadgeClass() }}">{{ $record->certificateState() }}</span></td>
                            <td>
                                @if ($record->isArchived())
                                    <span class="badge">Archived</span><br><small class="meta">Archived {{ $record->archived_at?->format('M j, Y') }}</small>
                                @else
                                    <span class="badge status-present">Active</span>
                                @endif
                            </td>
                            <td>
                                <div class="medical-actions">
                                    <a class="row-action" href="{{ route('admin.medical.show', $record) }}">View</a>
                                    <a class="row-action" href="{{ route('admin.medical.edit', $record) }}">Edit</a>
                                    @if ($record->isArchived())
                                        <button class="row-action" type="button" data-confirm-dialog data-confirm-title="Restore Medical Record?" data-confirm-message="Restore this medical record to the active list?" data-confirm-label="Restore" data-confirm-icon="check" data-confirm-variant="success" data-confirm-method="PATCH" data-confirm-url="{{ route('admin.medical.restore', $record) }}">Restore</button>
                                        <button class="row-action row-action-danger" type="button" data-confirm-dialog data-confirm-title="Delete Medical Record?" data-confirm-message="Permanently delete this medical record? This cannot be undone." data-confirm-label="Delete" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.medical.destroy', $record) }}">Delete</button>
                                    @else
                                        <button class="row-action" type="button" data-confirm-dialog data-confirm-title="Archive Medical Record?" data-confirm-message="Archive this medical record? It will be removed from the active list." data-confirm-label="Archive" data-confirm-icon="warning" data-confirm-variant="warning" data-confirm-method="PATCH" data-confirm-url="{{ route('admin.medical.archive', $record) }}">Archive</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-cell">
                                <p class="empty-state">No medical records found.</p>
                                <a class="button button-secondary" href="{{ route('admin.medical.create') }}">Add Medical Record</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid medical-panels medical-tracker-grid">
        <section class="card medical-tracker-card">
            <div class="medical-tracker-head">
                <span class="medical-kpi-icon tone-amber"><svg aria-hidden="true"><use href="#icon-certificate"></use></svg></span>
                <div>
                    <h2>Expiring Soon</h2>
                    <p>Deadlines within the next {{ $medicalExpiringDays }} days</p>
                </div>
                <span class="medical-count-chip">{{ $expiringCertificates->count() }}</span>
            </div>
            <div class="medical-tracker-list">
                @forelse ($expiringCertificates as $record)
                    <div class="medical-tracker-row">
                        <span class="avatar avatar-initials medical-avatar" aria-hidden="true">{{ filled($record->athlete?->name ?? '') ? $initials($record->athlete->name) : '?' }}</span>
                        <div class="medical-tracker-main">
                            <a class="text-link" href="{{ route('admin.medical.show', $record) }}">{{ $record->athlete?->name ?? 'Unknown athlete' }}</a>
                            <small class="meta">
                                {{ $record->sportName() }}
                                @if ($record->athlete?->student_id) &middot; {{ $record->athlete->student_id }} @endif
                            </small>
                        </div>
                        <div class="medical-tracker-right">
                            <strong>{{ $record->nextCheckupLabel() }}</strong>
                            <span class="{{ $record->certificateBadgeClass() }}">{{ $record->certificateState() }}</span>
                        </div>
                    </div>
                @empty
                    <p class="empty-state">No medical certificates are expiring soon.</p>
                @endforelse
            </div>
        </section>

        <section class="card medical-tracker-card">
            <div class="medical-tracker-head">
                <span class="medical-kpi-icon tone-gold"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
                <div>
                    <h2>Upcoming Checkups</h2>
                    <p>Clearances due in the weeks ahead</p>
                </div>
                <span class="medical-count-chip">{{ $upcomingCheckups->count() }}</span>
            </div>
            <div class="medical-tracker-list">
                @forelse ($upcomingCheckups as $record)
                    <div class="medical-tracker-row">
                        <span class="avatar avatar-initials medical-avatar" aria-hidden="true">{{ filled($record->athlete?->name ?? '') ? $initials($record->athlete->name) : '?' }}</span>
                        <div class="medical-tracker-main">
                            <a class="text-link" href="{{ route('admin.medical.show', $record) }}">{{ $record->athlete?->name ?? 'Unknown athlete' }}</a>
                            <small class="meta">{{ $record->sportName() }}</small>
                        </div>
                        <div class="medical-tracker-right">
                            <strong>{{ $record->nextCheckupLabel() }}</strong>
                            <span class="{{ $record->statusBadgeClass() }}">{{ $record->medical_status }}</span>
                        </div>
                    </div>
                @empty
                    <p class="empty-state">No upcoming checkups.</p>
                @endforelse
            </div>
        </section>

        <section class="card medical-tracker-card">
            <div class="medical-tracker-head">
                <span class="medical-kpi-icon tone-danger"><svg aria-hidden="true"><use href="#icon-bell"></use></svg></span>
                <div>
                    <h2>Overdue Checkups</h2>
                    <p>Clearances that require immediate action</p>
                </div>
                <span class="medical-count-chip">{{ $overdueCheckups->count() }}</span>
            </div>
            <div class="medical-tracker-list">
                @forelse ($overdueCheckups as $record)
                    <div class="medical-tracker-row">
                        <span class="avatar avatar-initials medical-avatar" aria-hidden="true">{{ filled($record->athlete?->name ?? '') ? $initials($record->athlete->name) : '?' }}</span>
                        <div class="medical-tracker-main">
                            <a class="text-link" href="{{ route('admin.medical.show', $record) }}">{{ $record->athlete?->name ?? 'Unknown athlete' }}</a>
                            <small class="meta">{{ $record->sportName() }}</small>
                        </div>
                        <div class="medical-tracker-right">
                            <strong>{{ $record->nextCheckupLabel() }}</strong>
                            <span class="{{ $record->statusBadgeClass() }}">{{ $record->medical_status }}</span>
                        </div>
                    </div>
                @empty
                    <p class="empty-state">No overdue checkups.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card medical-incidents-card" id="incident-form">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">{{ $medicalIncidentFormMode === 'edit' ? 'UPDATE INCIDENT' : 'NEW INCIDENT' }}</span>
                <h2>{{ $medicalIncidentFormMode === 'edit' ? 'Edit Injury / Medical Incident' : 'Record Injury / Medical Incident' }}</h2>
                <p>Injuries are stored per athlete, so repeated examinations never overwrite the injury history.</p>
            </div>
            @if ($medicalIncidentFormMode === 'edit')
                <a class="text-link" href="{{ route('admin.medical') }}">Cancel</a>
            @endif
        </div>

        <form method="POST" action="{{ $medicalIncidentFormMode === 'edit' ? route('admin.medical.incidents.update', $medicalIncidentFormRecord) : route('admin.medical.incidents.store') }}" class="medical-form-body">
            @csrf
            @if ($medicalIncidentFormMode === 'edit') @method('PUT') @endif

            <div class="grid grid-3">
                <div class="form-group">
                    <label for="incident-athlete">Athlete</label>
                    <select class="form-control @error('athlete_id') is-invalid @enderror" id="incident-athlete" name="athlete_id" required>
                        <option value="">Select athlete</option>
                        @foreach ($athletes ?? [] as $option)
                            <option value="{{ $option->id }}" @selected((string) old('athlete_id', $medicalIncidentFormRecord?->athlete_id ?? '') === (string) $option->id)>
                                {{ $option->name }}@if ($option->student_id) ({{ $option->student_id }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('athlete_id')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="incident-sport">Sport</label>
                    <select class="form-control @error('sport_id') is-invalid @enderror" id="incident-sport" name="sport_id">
                        <option value="">Use the athlete's sport</option>
                        @foreach ($sports ?? [] as $option)
                            <option value="{{ $option->id }}" @selected((string) old('sport_id', $medicalIncidentFormRecord?->sport_id ?? '') === (string) $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('sport_id')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="incident-date">Incident Date</label>
                    <input class="form-control @error('incident_date') is-invalid @enderror" id="incident-date" type="date" name="incident_date" value="{{ old('incident_date', $medicalIncidentFormRecord?->incident_date?->format('Y-m-d') ?? '') }}" required>
                    @error('incident_date')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="grid grid-3">
                <div class="form-group">
                    <label for="incident-injury-type">Injury / Condition</label>
                    <input class="form-control @error('injury_type') is-invalid @enderror" id="incident-injury-type" type="text" name="injury_type" maxlength="100" placeholder="e.g. Ankle sprain" value="{{ old('injury_type', $medicalIncidentFormRecord?->injury_type ?? '') }}" required>
                    @error('injury_type')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="incident-body-part">Body Part</label>
                    <input class="form-control @error('body_part') is-invalid @enderror" id="incident-body-part" type="text" name="body_part" maxlength="100" placeholder="e.g. Right ankle" value="{{ old('body_part', $medicalIncidentFormRecord?->body_part ?? '') }}">
                    @error('body_part')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="incident-activity">Activity</label>
                    <input class="form-control @error('activity') is-invalid @enderror" id="incident-activity" type="text" name="activity" maxlength="150" placeholder="e.g. Training drill" value="{{ old('activity', $medicalIncidentFormRecord?->activity ?? '') }}">
                    @error('activity')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="grid grid-3">
                <div class="form-group">
                    <label for="incident-severity">Severity</label>
                    <select class="form-control @error('severity') is-invalid @enderror" id="incident-severity" name="severity" required>
                        @foreach ($medicalSeverities as $level)
                            <option value="{{ $level }}" @selected(old('severity', $medicalIncidentFormRecord?->severity ?? 'Mild') === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                    @error('severity')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="incident-rest-period">Rest Period (days)</label>
                    <input class="form-control @error('rest_period_days') is-invalid @enderror" id="incident-rest-period" type="number" name="rest_period_days" min="0" max="365" value="{{ old('rest_period_days', $medicalIncidentFormRecord?->rest_period_days ?? '') }}">
                    @error('rest_period_days')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="incident-return-date">Return to Play</label>
                    <input class="form-control @error('return_to_play_date') is-invalid @enderror" id="incident-return-date" type="date" name="return_to_play_date" value="{{ old('return_to_play_date', $medicalIncidentFormRecord?->return_to_play_date?->format('Y-m-d') ?? '') }}">
                    @error('return_to_play_date')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            </div>

            @can('view-medical-details')
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="incident-description">What Happened</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="incident-description" name="description" rows="3" maxlength="2000" placeholder="Describe the injury or incident">{{ old('description', $medicalIncidentFormRecord?->description ?? '') }}</textarea>
                        @error('description')<small class="form-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-group">
                        <label for="incident-treatment">Treatment</label>
                        <textarea class="form-control @error('treatment') is-invalid @enderror" id="incident-treatment" name="treatment" rows="3" maxlength="2000" placeholder="Treatment given">{{ old('treatment', $medicalIncidentFormRecord?->treatment ?? '') }}</textarea>
                        @error('treatment')<small class="form-error">{{ $message }}</small>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="incident-clearance">Medical Clearance</label>
                    <select class="form-control @error('medical_clearance') is-invalid @enderror" id="incident-clearance" name="medical_clearance" required>
                        @foreach ($medicalClearances as $state)
                            <option value="{{ $state }}" @selected(old('medical_clearance', $medicalIncidentFormRecord?->medical_clearance ?? 'Pending') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <small class="form-hint">Athletes without a Cleared clearance and without a past return date are counted as under recovery.</small>
                    @error('medical_clearance')<small class="form-error">{{ $message }}</small>@enderror
                </div>
            @endcan

            <div class="medical-form-actions">
                <button class="button" type="submit">{{ $medicalIncidentFormMode === 'edit' ? 'Update Incident' : 'Save Incident' }}</button>
                @if ($medicalIncidentFormMode === 'edit')
                    <a class="button button-secondary" href="{{ route('admin.medical') }}">Cancel</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card medical-incidents-card">
        <div class="medical-card-head">
            <div>
                <span class="admin-dashboard-kicker">INJURY LOG</span>
                <h2>Injuries &amp; Medical Incidents</h2>
                <p>{{ count($medicalIncidents) }} incident{{ count($medicalIncidents) === 1 ? '' : 's' }} on record</p>
            </div>
            <span class="medical-count-chip">{{ count($medicalIncidents) }}</span>
        </div>

        <div class="table-wrap">
            <table class="data-table medical-incidents-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Athlete</th>
                        <th>Sport</th>
                        <th>Injury</th>
                        <th>Severity</th>
                        <th>Clearance</th>
                        <th>Return to Play</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($medicalIncidents as $incident)
                        <tr>
                            <td>{{ $incident->incidentDateLabel() }}</td>
                            <td>
                                <div class="medical-cell">
                                    <span class="avatar avatar-initials medical-avatar" aria-hidden="true">{{ filled($incident->athlete?->name ?? '') ? $initials($incident->athlete->name) : '?' }}</span>
                                    <div>
                                        <strong>{{ $incident->athlete?->name ?? 'Unknown athlete' }}</strong>
                                        @if ($incident->athlete?->student_id)<br><small class="meta">{{ $incident->athlete->student_id }}</small>@endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $incident->sportName() }}</td>
                            <td>
                                <strong>{{ $incident->injury_type }}</strong>
                                @if ($incident->body_part)<br><small class="meta">{{ $incident->body_part }}</small>@endif
                                @if ($incident->activity)<br><small class="meta">{{ $incident->activity }}</small>@endif
                            </td>
                            <td><span class="{{ $incident->severityBadgeClass() }}">{{ $incident->severity }}</span></td>
                            <td><span class="{{ $incident->clearanceBadgeClass() }}">{{ $incident->medical_clearance }}</span></td>
                            <td>{{ $incident->returnToPlayLabel() }}</td>
                            <td>
                                <div class="medical-actions">
                                        <a class="row-action" href="{{ route('admin.medical').'?incident='.$incident->id }}">Edit</a>
                                        <button class="row-action row-action-danger" type="button" data-confirm-dialog data-confirm-title="Delete Medical Incident?" data-confirm-message="Delete this injury / medical incident?" data-confirm-label="Delete" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.medical.incidents.destroy', $incident) }}">Delete</button>
                                    </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-cell"><p class="empty-state">No injuries or medical incidents recorded.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif