@php($certificateTotals = $certificateTotals ?? ['pending' => 0, 'approved' => 0, 'issued' => 0, 'rejected' => 0])
@php($sectionGroups = [
    'PENDING' => ['key' => 'certificatePending', 'label' => 'Approval Needed', 'hint' => 'Certificate requests waiting for your review', 'status' => 'pending'],
    'APPROVED' => ['key' => 'certificateApproved', 'label' => 'Approved', 'hint' => 'Approved and ready for the certificate to be issued', 'status' => 'approved'],
    'ISSUED' => ['key' => 'certificateIssued', 'label' => 'Issued', 'hint' => 'Certificates that have been generated and delivered', 'status' => 'issued'],
    'REJECTED' => ['key' => 'certificateRejected', 'label' => 'Rejected', 'hint' => 'Requests that were declined', 'status' => 'rejected'],
])

<div class="grid cert-request-summary">
    <div class="card stat cert-request-tile"><small>PENDING</small><strong>{{ $certificateTotals['pending'] }}</strong></div>
    <div class="card stat cert-request-tile"><small>APPROVED</small><strong>{{ $certificateTotals['approved'] }}</strong></div>
    <div class="card stat cert-request-tile"><small>ISSUED</small><strong>{{ $certificateTotals['issued'] }}</strong></div>
    <div class="card stat cert-request-tile"><small>REJECTED</small><strong>{{ $certificateTotals['rejected'] }}</strong></div>
</div>

@foreach ($sectionGroups as $groupLabel => $section)
    @php($items = ${$section['key']})
    <section class="card cert-request-group">
        <div class="section-heading">
            <div>
                <span class="admin-dashboard-kicker">{{ $groupLabel }}</span>
                <h2>{{ $section['label'] }}</h2>
                <p>{{ $section['hint'] }}</p>
            </div>
            <span class="badge status-{{ $section['status'] }}">{{ $items->count() }}</span>
        </div>

        @if ($items->isEmpty())
            <p class="empty-state">No {{ strtolower($section['label']) }} certificate requests.</p>
        @else
            <div class="cert-request-list">
                @foreach ($items as $request)
                    <div class="cert-request-item">
                        <div class="cert-request-identity">
                            <strong>{{ $request->student?->name ?? ($request->achievement?->team_name ?? 'Team achievement') }}</strong>
                            @if ($request->student?->student_id)<span class="meta">{{ $request->student->student_id }}</span>@endif
                            <span class="badge status-{{ $section['status'] }}">{{ $request->status }}</span>
                        </div>
                        <div class="cert-request-detail">
                            <strong>{{ $request->achievement?->title }}</strong>
                            <span class="meta">{{ $request->achievement?->sportLabel() }} &middot; requested {{ $request->created_at?->format('M j, Y') }}</span>
                            <span class="meta cert-request-generation">
                                @if ($request->isGenerated())
                                    PDF generated {{ $request->certificate_generated_at?->format('M j, Y g:i A') }} &middot; Ref {{ $request->certificateReference() }}
                                @else
                                    Certificate not generated yet
                                @endif
                            </span>
                            @if ($request->remarks)
                                <p class="cert-request-remarks">&ldquo;{{ $request->remarks }}&rdquo;</p>
                            @endif
                        </div>
                        <div class="cert-request-actions">
                            @if ($request->status === 'Pending')
                                <button class="button" type="button" data-confirm-dialog data-confirm-title="Approve Certificate Request?" data-confirm-message="Approve this certificate request?" data-confirm-label="Approve" data-confirm-icon="check" data-confirm-variant="success" data-confirm-method="POST" data-confirm-url="{{ route('admin.certificate-requests.approve', $request) }}">Approve</button>
                                <form method="POST" action="{{ route('admin.certificate-requests.reject', $request) }}">
                                    @csrf
                                    <input class="cert-request-reject-reason" name="remarks" type="text" placeholder="Reason (optional)" maxlength="255">
                                    <button class="button button-danger" type="button" data-confirm-dialog data-confirm-title="Reject Certificate Request?" data-confirm-message="Reject this certificate request? You can include a reason for the applicant." data-confirm-label="Reject" data-confirm-icon="warning" data-confirm-variant="danger" data-confirm-method="POST" data-confirm-url="{{ route('admin.certificate-requests.reject', $request) }}">Reject</button>
                                </form>
                            @elseif ($request->status === 'Approved')
                                @if ($request->isGenerated())
                                    <a class="text-link" href="{{ route('admin.achievements.certificate', $request->achievement) }}">Download PDF</a>
                                    <button class="button button-secondary" type="button" data-confirm-dialog data-confirm-title="Regenerate Certificate?" data-confirm-message="Generate the certificate again? The previous file is kept in the certificate history." data-confirm-label="Regenerate" data-confirm-icon="file" data-confirm-variant="info" data-confirm-method="POST" data-confirm-url="{{ route('admin.certificate-requests.generate', $request) }}">Regenerate PDF</button>
                                    <button class="button" type="button" data-confirm-dialog data-confirm-title="Release Certificate?" data-confirm-message="Mark this certificate as ready for release? The athlete can download it from then on." data-confirm-label="Mark Ready" data-confirm-icon="check" data-confirm-variant="success" data-confirm-method="POST" data-confirm-url="{{ route('admin.certificate-requests.release', $request) }}">Mark Ready for Release</button>
                                @else
                                    <button class="button" type="button" data-confirm-dialog data-confirm-title="Generate Certificate?" data-confirm-message="Generate the official PDF for this request? You can review it before releasing it." data-confirm-label="Generate PDF" data-confirm-icon="file" data-confirm-variant="info" data-confirm-method="POST" data-confirm-url="{{ route('admin.certificate-requests.generate', $request) }}">Generate PDF</button>
                                    <button class="button button-secondary" type="button" data-confirm-dialog data-confirm-title="Issue Certificate?" data-confirm-message="Generate and release this official certificate now?" data-confirm-label="Issue Certificate" data-confirm-icon="file" data-confirm-variant="info" data-confirm-method="POST" data-confirm-url="{{ route('admin.certificate-requests.issue', $request) }}">Issue Certificate</button>
                                @endif
                            @elseif ($request->status === 'Issued')
                                <a class="text-link" href="{{ route('admin.achievements.show', $request->achievement) }}">View Achievement</a>
                                <a class="text-link" href="{{ route('admin.achievements.certificate', $request->achievement) }}">Download PDF</a>
                            @else
                                <span class="meta">Declined</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endforeach