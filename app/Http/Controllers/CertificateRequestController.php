<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\CertificateRequest;
use App\Services\AchievementCertificateService;
use App\Services\StudentUpdateNotifier;
use App\Support\PortalNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CertificateRequestController extends Controller
{
    /**
     * The folder issued certificates are written to on the private disk.
     */
    private const CERTIFICATE_FOLDER = 'achievement-certificates';

    /**
     * The folder superseded certificates are moved to, so a regenerated
     * certificate never silently destroys the previous file.
     */
    private const HISTORY_FOLDER = 'achievement-certificates/history';

    public function index(): View
    {
        $this->ensureAdministrator();

        $requests = CertificateRequest::query()
            ->with(['achievement.sport', 'achievement.athlete:id,name,student_id', 'student:id,name,student_id', 'handler:id,name'])
            ->latest()
            ->get();

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'certificate-requests',
            'heading' => 'Certificate Requests',
            'subtitle' => 'Review athlete certificate requests, approve them and issue the official PDF',
            'certificatePending' => $requests->where('status', CertificateRequest::STATUS_PENDING),
            'certificateApproved' => $requests->where('status', CertificateRequest::STATUS_APPROVED),
            'certificateIssued' => $requests->where('status', CertificateRequest::STATUS_ISSUED),
            'certificateRejected' => $requests->where('status', CertificateRequest::STATUS_REJECTED),
            'certificateTotals' => [
                'pending' => $requests->where('status', CertificateRequest::STATUS_PENDING)->count(),
                'approved' => $requests->where('status', CertificateRequest::STATUS_APPROVED)->count(),
                'issued' => $requests->where('status', CertificateRequest::STATUS_ISSUED)->count(),
                'rejected' => $requests->where('status', CertificateRequest::STATUS_REJECTED)->count(),
            ],
        ]));
    }

    public function approve(CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing('achievement');

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_PENDING, 422, 'Only pending requests can be approved.');
        $this->ensureNotOwnRequest($certificateRequest);

        $certificateRequest->update([
            'status' => CertificateRequest::STATUS_APPROVED,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return back()->with('success', 'Certificate request approved. You can now generate the certificate.');
    }

    /**
     * Generate the certificate PDF for an approved request.
     *
     * The file is written to the private disk and linked to both the request
     * and the achievement. The request stays approved until an administrator
     * marks it ready for release.
     */
    public function generate(CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing(['achievement.athlete:id,name,student_id']);

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_APPROVED, 422, 'Only approved requests can be generated.');
        $this->ensureNotOwnRequest($certificateRequest);

        $achievement = $this->guardRelationships($certificateRequest);
        $regenerated = $certificateRequest->isGenerated();

        $this->generatePdf($certificateRequest, $achievement);

        return back()->with('success', $regenerated
            ? 'The certificate was regenerated. The previous file was kept in the certificate history.'
            : 'Certificate generated. Review it, then mark it ready for release.');
    }

    /**
     * Mark a generated certificate as ready for release, so the student can
     * download it.
     */
    public function release(CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing(['achievement.athlete:id,name,student_id']);

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_APPROVED, 422, 'Only approved requests can be released.');
        abort_if(! $certificateRequest->isGenerated(), 422, 'Generate the certificate before releasing it.');
        $this->ensureNotOwnRequest($certificateRequest);

        $certificateRequest->update([
            'status' => CertificateRequest::STATUS_ISSUED,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        $this->notifyIssued($certificateRequest);

        return back()->with('success', 'Certificate marked as ready for release. The athlete can download it now.');
    }

    /**
     * Generate and release in one action.
     */
    public function issue(CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing(['achievement.athlete:id,name,student_id']);

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_APPROVED, 422, 'Only approved requests can be issued.');
        $this->ensureNotOwnRequest($certificateRequest);

        $achievement = $this->guardRelationships($certificateRequest);

        $this->generatePdf($certificateRequest, $achievement);

        $certificateRequest->update([
            'status' => CertificateRequest::STATUS_ISSUED,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        $this->notifyIssued($certificateRequest);

        return back()->with('success', 'Certificate issued to the athlete.');
    }

    public function reject(Request $request, CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing('achievement');

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_PENDING, 422, 'Only pending requests can be rejected.');
        $this->ensureNotOwnRequest($certificateRequest);

        $remarks = trim((string) $request->input('remarks', ''));

        $certificateRequest->update([
            'status' => CertificateRequest::STATUS_REJECTED,
            'remarks' => $remarks ?: null,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        app(StudentUpdateNotifier::class)->notifyStudent(
            $certificateRequest->student,
            'Certificate Request Declined',
            $remarks ?: 'Your certificate request was declined.',
            route('student.achievements.show', $certificateRequest->achievement),
            ['dedupe_key' => 'certificate|rejected|'.$certificateRequest->id],
        );

        return back()->with('success', 'Certificate request rejected.');
    }

    /**
     * Render the official certificate to the private disk and link it to both
     * the request and the achievement, so the athlete can open it immediately
     * and no second request for the same achievement can be submitted.
     */
    private function generatePdf(CertificateRequest $certificateRequest, Achievement $achievement): void
    {
        $service = app(AchievementCertificateService::class);
        $path = self::CERTIFICATE_FOLDER.'/'.Str::uuid().'.pdf';

        $contents = $service->outputPdf($achievement, $certificateRequest);

        // A regenerated certificate keeps the file it replaces in the history
        // folder instead of overwriting it.
        if ($certificateRequest->certificate_path && Storage::disk('private')->exists($certificateRequest->certificate_path)) {
            Storage::disk('private')->move($certificateRequest->certificate_path, self::HISTORY_FOLDER.'/'.Str::uuid().'.pdf');
        }

        Storage::disk('private')->put($path, $contents);

        $certificateRequest->update([
            'certificate_path' => $path,
            'certificate_generated_at' => now(),
            'certificate_reference' => $service->referenceFor($achievement),
        ]);

        $achievement->update(['certificate_path' => $path]);
    }

    /**
     * The request has to point at an achievement that still exists, and a
     * request for an individual achievement has to belong to that athlete.
     */
    private function guardRelationships(CertificateRequest $certificateRequest): Achievement
    {
        $achievement = $certificateRequest->achievement()->first();

        abort_if(! $achievement, 422, 'The achievement for this request no longer exists.');
        abort_if(
            ! $achievement->isTeam() && (int) $achievement->athlete_id !== (int) $certificateRequest->student_id,
            422,
            'This request does not belong to the achievement it references.'
        );

        return $achievement;
    }
    /**
     * An administrator never approves, generates or releases their own
     * request.
     */
    private function ensureNotOwnRequest(CertificateRequest $certificateRequest): void
    {
        abort_if(
            (int) $certificateRequest->student_id === (int) auth()->id(),
            403,
            'You cannot action a certificate request that belongs to you.'
        );
    }

    /**
     * Tell the athlete the certificate is ready to download.
     */
    private function notifyIssued(CertificateRequest $certificateRequest): void
    {
        app(StudentUpdateNotifier::class)->notifyStudent(
            $certificateRequest->student,
            'Certificate Issued',
            'Your certificate for "'.$certificateRequest->achievement?->title.'" is ready to view.',
            route('student.achievements.show', $certificateRequest->achievement),
            ['dedupe_key' => 'certificate|issued|'.$certificateRequest->achievement_id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function portalData(): array
    {
        return array_merge([
            'title' => 'Certificate Requests',
            'navItems' => PortalNavigation::admin(),
            'active' => 'certificate-requests',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
        ]);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }
}