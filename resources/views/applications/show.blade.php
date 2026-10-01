@extends('layouts.portal')

@section('content')
<div class="module-header">
    <div><h1>Application Review</h1><p class="page-subtitle">Review applicant information and eligibility documents</p></div>
    <div class="event-header-actions">
        <a class="button button-secondary" href="{{ route('admin.applications') }}">Back</a>
        @if ($application->status === 'Approved' && !$application->athlete)
            <a class="button" href="{{ route('athletes.create', $application) }}">Add to Athletes</a>
        @endif
    </div>
</div>
@if (session('success'))<div class="notice">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
@php($documentDefinitions = \App\Http\Controllers\ApplicationController::documentDefinitions())

<section class="application-detail-grid">
    <div class="card">
        <h2>Applicant Information</h2>
        <div class="application-detail-list">
            <span><strong>Name</strong>{{ $application->name }}</span>
            <span><strong>Email</strong>{{ $application->email }}</span>
            <span><strong>Student ID</strong>{{ $application->student_id ?: 'Not provided' }}</span>
            <span><strong>Grade</strong>{{ $application->grade }}</span>
            <span><strong>Gender</strong>{{ $application->gender ?: 'Not provided' }}</span>
            <span><strong>Sport</strong>{{ $application->sportCategory?->name ?? $application->sport ?: 'Unassigned' }}</span>
            <span><strong>Applied</strong>{{ $application->created_at?->format('F j, Y') }}</span>
            <span><strong>Athlete Profile</strong>{{ $application->athlete?->name ?? 'Not created yet' }}</span>
        </div>
    </div>
    <div class="card">
        <h2>Application Status</h2>
        <span class="badge application-status-{{ strtolower(str_replace(' ', '-', $application->status)) }}">{{ $application->status }}</span>
        @if ($application->rejection_reason)
            <div class="review-note"><strong>Rejection reason</strong><p>{{ $application->rejection_reason }}</p></div>
        @endif
        @if ($application->review_notes)
            <div class="review-note"><strong>Review notes</strong><p>{{ $application->review_notes }}</p></div>
        @endif
        @if ($application->reviewed_at)
            <p class="meta">Reviewed {{ $application->reviewed_at->format('M j, Y g:i A') }}@if ($application->reviewer) by {{ $application->reviewer->name }}@endif</p>
        @endif
    </div>
</section>

<section class="card application-review-card">
    <div class="section-heading"><div><h2>Eligibility Documents</h2><p class="meta">Documents remain private and can only be accessed by authorized administrators.</p></div></div>
    @foreach ($documentDefinitions as $key => $document)
        @php($documentStatus = $application->{$document['status']} ?: ($application->{$document['path']} ? 'Submitted' : 'Missing'))
        <div class="document-review-row">
            <div>
                <strong>{{ $document['label'] }}</strong>
                <span class="document-badge document-{{ strtolower($documentStatus) }}">{{ $documentStatus }}</span>
                @if (($application->document_rejection_notes ?? [])[$key] ?? false)
                    <p class="meta">{{ $application->document_rejection_notes[$key] }}</p>
                @endif
            </div>
            <div class="document-actions">
                @if ($application->{$document['path']})
                    <a class="text-link" href="{{ route('applications.documents.download', [$application, $key]) }}">View</a>
                    @if ($documentStatus !== 'Verified')
                        <form method="POST" action="{{ route('applications.documents.verify', [$application, $key]) }}">@csrf<button class="button button-secondary" type="submit">Verify</button></form>
                    @endif
                    <form method="POST" action="{{ route('applications.documents.reject', [$application, $key]) }}" class="inline-form">@csrf<input class="form-control document-reason-input" name="reason" placeholder="Rejection reason" required><button class="button button-danger" type="submit">Reject</button></form>
                @else
                    <span class="meta">Missing</span>
                @endif
            </div>
        </div>
    @endforeach
</section>

<section class="application-detail-grid">
    <div class="card">
        <h2>Review Actions</h2>
        <div class="review-action-stack">
            @if ($application->status !== 'Approved')
                <form method="POST" action="{{ route('applications.approve', $application) }}" onsubmit="return confirm('Approve this application? Required documents must be verified.');">@csrf<input class="form-control" name="review_notes" placeholder="Optional approval notes"><button class="button" type="submit">Approve Application</button></form>
            @endif
            <form method="POST" action="{{ route('applications.reject', $application) }}">@csrf<input class="form-control" name="rejection_reason" placeholder="Rejection reason" required><input class="form-control" name="review_notes" placeholder="Optional review notes"><button class="button button-danger" type="submit">Reject Application</button></form>
            <form method="POST" action="{{ route('applications.update', $application) }}">@csrf @method('PATCH')<select class="form-control" name="status">@foreach (\App\Http\Controllers\ApplicationController::statuses() as $status)<option value="{{ $status }}" @selected($application->status === $status)>{{ $status }}</option>@endforeach</select><textarea class="form-control" name="review_notes" placeholder="Review notes">{{ $application->review_notes }}</textarea><button class="button button-secondary" type="submit">Update Status</button></form>
        </div>
    </div>
    <div class="card">
        <h2>Request Documents</h2>
        <form method="POST" action="{{ route('applications.request-documents', $application) }}">@csrf<label class="check-row"><input type="checkbox" name="documents[]" value="medical"> Medical Certificate</label><label class="check-row"><input type="checkbox" name="documents[]" value="birth"> Birth Certificate</label><label class="check-row"><input type="checkbox" name="documents[]" value="consent"> Parent/Guardian Consent</label><textarea class="form-control" name="message" placeholder="Tell the applicant what needs to be corrected" required></textarea><button class="button button-secondary" type="submit">Request Documents</button></form>
    </div>
</section>

<section class="card application-history">
    <h2>Application History</h2>
    @forelse (array_reverse($application->review_history ?? []) as $history)
        <div class="history-row"><strong>{{ $history['label'] }}</strong><span>{{ \Carbon\Carbon::parse($history['at'])->format('M j, Y g:i A') }}</span></div>
    @empty
        <p class="empty-state">No review history recorded yet.</p>
    @endforelse
</section>
@endsection
