@php
    $documentDefinitions = $appController::documentDefinitions();
    $sportLabel = $application->sportCategory?->name ?? $application->sport ?: 'Unassigned';
    $documentState = fn (string $path, string $state): string => filled($application->{$path}) ? ($application->{$state} ?: 'Submitted') : 'Missing';
    $docsVerified = collect($documentDefinitions)->every(
        fn (array $definition) => filled($application->{$definition['path']}) && $application->{$definition['status']} === 'Verified'
    );
@endphp

{{-- Approve application ------------------------------------------------------- --}}
<dialog class="app-modal" id="app-modal-approve" data-app-modal aria-labelledby="app-modal-approve-title" aria-describedby="app-modal-approve-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="{{ route('applications.approve', $application) }}" data-app-modal-form>
        @csrf
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--success"><svg aria-hidden="true"><use href="#icon-check"></use></svg></span>
                <div class="app-modal-title">
                    <h2 id="app-modal-approve-title">Approve Application</h2>
                    <p>Approve this athlete once their eligibility documents are verified.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" id="app-modal-approve-desc">Are you sure you want to approve this athlete application?</p>
                <dl class="app-modal-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->name }}</dd></div>
                    <div><dt>Sport</dt><dd>{{ $sportLabel }}</dd></div>
                    <div><dt>Application status</dt><dd>{{ $application->status }}</dd></div>
                </dl>
                <div class="app-modal-section">
                    <strong class="app-modal-section-label">Required documents</strong>
                    <ul class="app-modal-docs">
                        @foreach ($documentDefinitions as $definition)
                            @php($state = $documentState($definition['path'], $definition['status']))
                            <li class="app-modal-doc is-{{ strtolower($state) }}">
                                <svg aria-hidden="true"><use href="#icon-{{ $state === 'Verified' ? 'check' : ($state === 'Missing' ? 'warning' : 'file') }}"></use></svg>
                                <span>{{ $definition['label'] }}</span>
                                <em class="document-badge document-{{ strtolower($state) }}">{{ $state }}</em>
                            </li>
                        @endforeach
                    </ul>
                    @unless ($docsVerified)
                        <div class="app-modal-warning"><svg aria-hidden="true"><use href="#icon-warning"></use></svg><span>All eligibility documents must be verified before the application can be approved.</span></div>
                    @endunless
                </div>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="approve-notes">Approval notes <span class="app-modal-optional">(optional)</span></label>
                    <textarea class="form-control" id="approve-notes" name="review_notes" rows="3" placeholder="Notes about this approval" data-app-modal-autofocus></textarea>
                </div>
                <p class="app-modal-note">This action will approve the application and update the athlete's application status.</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to approve application</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-secondary" type="button" data-app-modal-close>Cancel</button>
                <button class="button app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Approving..." @disabled(! $docsVerified)>Approve Application</button>
            </footer>
        </div>
    </form>
</dialog>

{{-- Move under review ---------------------------------------------------------- --}}
<dialog class="app-modal" id="app-modal-review" data-app-modal aria-labelledby="app-modal-review-title" aria-describedby="app-modal-review-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="{{ route('applications.update', $application) }}" data-app-modal-form>
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="Under Review">
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--info"><svg aria-hidden="true"><use href="#icon-eye"></use></svg></span>
                <div class="app-modal-title">
                    <h2 id="app-modal-review-title">Move Application Under Review</h2>
                    <p>Let the applicant know their application is being reviewed.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" id="app-modal-review-desc">Move this application to Under Review? The applicant will keep their current eligibility documents.</p>
                <dl class="app-modal-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->name }}</dd></div>
                    <div><dt>Sport</dt><dd>{{ $sportLabel }}</dd></div>
                    <div><dt>Application status</dt><dd>{{ $application->status }}</dd></div>
                </dl>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="review-notes">Review notes <span class="app-modal-optional">(optional)</span></label>
                    <textarea class="form-control" id="review-notes" name="review_notes" rows="3" placeholder="Notes for the review record" data-app-modal-autofocus></textarea>
                </div>
                <p class="app-modal-note">The status will change to Under Review and the applicant will be notified.</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to update status</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-secondary" type="button" data-app-modal-close>Cancel</button>
                <button class="button app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Updating...">Move to Under Review</button>
            </footer>
        </div>
    </form>
</dialog>

{{-- Place on waitlist ----------------------------------------------------------- --}}
<dialog class="app-modal" id="app-modal-waitlist" data-app-modal aria-labelledby="app-modal-waitlist-title" aria-describedby="app-modal-waitlist-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="{{ route('applications.update', $application) }}" data-app-modal-form>
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="Waitlisted">
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--info"><svg aria-hidden="true"><use href="#icon-users"></use></svg></span>
                <div class="app-modal-title">
                    <h2 id="app-modal-waitlist-title">Place Application on Waitlist</h2>
                    <p>Keep the application active while the athlete waits for a slot.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" id="app-modal-waitlist-desc">Place this application on the waitlist? You can revisit it later.</p>
                <dl class="app-modal-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->name }}</dd></div>
                    <div><dt>Sport</dt><dd>{{ $sportLabel }}</dd></div>
                    <div><dt>Application status</dt><dd>{{ $application->status }}</dd></div>
                </dl>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="waitlist-notes">Waitlist notes <span class="app-modal-optional">(optional)</span></label>
                    <textarea class="form-control" id="waitlist-notes" name="review_notes" rows="3" placeholder="Notes for the waitlist record" data-app-modal-autofocus></textarea>
                </div>
                <p class="app-modal-note">The status will change to Waitlisted and the applicant will be notified.</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to update status</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-secondary" type="button" data-app-modal-close>Cancel</button>
                <button class="button app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Updating...">Place on Waitlist</button>
            </footer>
        </div>
    </form>
</dialog>

{{-- Reject application ----------------------------------------------------------- --}}
<dialog class="app-modal" id="app-modal-reject" data-app-modal aria-labelledby="app-modal-reject-title" aria-describedby="app-modal-reject-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="{{ route('applications.reject', $application) }}" data-app-modal-form>
        @csrf
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--danger"><svg aria-hidden="true"><use href="#icon-x"></use></svg></span>
                <div class="app-modal-title">
                    <h2 id="app-modal-reject-title">Reject Application</h2>
                    <p>The applicant will be notified of the rejection and the reason.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" id="app-modal-reject-desc">You are about to reject this application. A rejection reason is required and cannot be changed later.</p>
                <dl class="app-modal-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->name }}</dd></div>
                    <div><dt>Sport</dt><dd>{{ $sportLabel }}</dd></div>
                    <div><dt>Application status</dt><dd>{{ $application->status }}</dd></div>
                </dl>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="reject-reason">Rejection reason <i class="app-modal-required">*</i></label>
                    <select class="form-control" id="reject-reason" name="rejection_reason" required data-app-reject-reason data-app-modal-autofocus>
                        <option value="" disabled selected>Select a reason...</option>
                        <option value="Incomplete documents">Incomplete documents</option>
                        <option value="Eligibility requirements not met">Eligibility requirements not met</option>
                        <option value="Submitted incorrect information">Submitted incorrect information</option>
                        <option value="Medical clearance not provided">Medical clearance not provided</option>
                        <option value="Slot unavailable">Slot unavailable</option>
                        <option value="Other">Other</option>
                    </select>
                    <div class="app-modal-other" data-app-reject-other-wrap hidden>
                        <label class="app-modal-label" for="reject-other">Describe the reason <i class="app-modal-required">*</i></label>
                        <textarea class="form-control" id="reject-other" name="rejection_reason_other" rows="2" placeholder="Provide more detail about this rejection" data-app-reject-other></textarea>
                    </div>
                </div>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="reject-notes">Additional notes <span class="app-modal-optional">(optional)</span></label>
                    <textarea class="form-control" id="reject-notes" name="review_notes" rows="3" placeholder="Notes for the review record"></textarea>
                </div>
                <p class="app-modal-note">This action will reject the application and record the reason for the applicant.</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to reject application</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-secondary" type="button" data-app-modal-close>Cancel</button>
                <button class="button button-danger app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Rejecting...">Reject Application</button>
            </footer>
        </div>
    </form>
</dialog>

{{-- Request documents ------------------------------------------------------------- --}}
<dialog class="app-modal" id="app-modal-documents" data-app-modal aria-labelledby="app-modal-documents-title" aria-describedby="app-modal-documents-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="{{ route('applications.request-documents', $application) }}" data-app-modal-form>
        @csrf
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--info"><svg aria-hidden="true"><use href="#icon-file"></use></svg></span>
                <div class="app-modal-title">
                    <h2 id="app-modal-documents-title">Request Documents</h2>
                    <p>Ask the applicant to resubmit specific eligibility documents.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" id="app-modal-documents-desc">Select the documents the applicant needs to resubmit, then add a short message.</p>
                <dl class="app-modal-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->name }}</dd></div>
                    <div><dt>Sport</dt><dd>{{ $sportLabel }}</dd></div>
                    <div><dt>Application status</dt><dd>{{ $application->status }}</dd></div>
                </dl>
                <div class="app-modal-section">
                    <strong class="app-modal-section-label">Select documents</strong>
                    <label class="check-row"><input type="checkbox" name="documents[]" value="medical" data-app-modal-autofocus> Medical Certificate</label>
                    <label class="check-row"><input type="checkbox" name="documents[]" value="birth"> Birth Certificate</label>
                    <label class="check-row"><input type="checkbox" name="documents[]" value="consent"> Parent/Guardian Consent</label>
                </div>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="request-message">Message for the applicant <i class="app-modal-required">*</i></label>
                    <textarea class="form-control" id="request-message" name="message" rows="3" placeholder="Tell the applicant what needs to be corrected" required></textarea>
                </div>
                <p class="app-modal-note">Submitting this request sets the application status to "Documents Required".</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to request documents</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-secondary" type="button" data-app-modal-close>Cancel</button>
                <button class="button app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Sending...">Send Request</button>
            </footer>
        </div>
    </form>
</dialog>

{{-- Generic status change ----------------------------------------------------------- --}}
<dialog class="app-modal" id="app-modal-status" data-app-modal aria-labelledby="app-modal-status-title" aria-describedby="app-modal-status-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="{{ route('applications.update', $application) }}" data-app-modal-form>
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="{{ $application->status }}" data-app-status-target>
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--warning"><svg aria-hidden="true"><use href="#icon-warning"></use></svg></span>
                <div class="app-modal-title">
                    <h2 id="app-modal-status-title">Update Application Status</h2>
                    <p>Confirm the application's new status before saving.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" id="app-modal-status-desc">Move this application to <strong data-app-status-value>{{ $application->status }}</strong>?</p>
                <dl class="app-modal-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->name }}</dd></div>
                    <div><dt>Sport</dt><dd>{{ $sportLabel }}</dd></div>
                    <div><dt>Current status</dt><dd>{{ $application->status }}</dd></div>
                </dl>
                <div class="app-modal-section">
                    <label class="app-modal-label" for="status-notes">Review notes <span class="app-modal-optional">(optional)</span></label>
                    <textarea class="form-control" id="status-notes" name="review_notes" rows="3" placeholder="Notes for the review record" data-app-modal-autofocus></textarea>
                </div>
                <p class="app-modal-note">The applicant will be notified of the new status.</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to update status</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-secondary" type="button" data-app-modal-close>Cancel</button>
                <button class="button app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Saving...">Update Status</button>
            </footer>
        </div>
    </form>
</dialog>