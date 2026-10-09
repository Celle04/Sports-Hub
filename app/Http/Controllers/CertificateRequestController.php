<?php

namespace App\Http\Controllers;

use App\Models\CertificateRequest;
use App\Notifications\CertificateRequestNotification;
use App\Services\AchievementCertificateService;
use App\Services\StudentUpdateNotifier;
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

        $certificateRequest->update([
            'status' => CertificateRequest::STATUS_APPROVED,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return back()->with('success', 'Certificate request approved. You can now issue the certificate.');
    }

    public function issue(CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing(['achievement.athlete:id,name,student_id']);

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_APPROVED, 422, 'Only approved requests can be issued.');

        $path = app(AchievementCertificateService::class);

        $this->issuePdf($certificateRequest, $path);

        app(StudentUpdateNotifier::class)->notifyStudent(
            $certificateRequest->student,
            'Certificate Issued',
            'Your certificate for "'.$certificateRequest->achievement?->title.'" is ready to view.',
            route('student.achievements.show', $certificateRequest->achievement),
            ['dedupe_key' => 'certificate|issued|'.$certificateRequest->achievement_id],
        );

        return back()->with('success', 'Certificate issued to the athlete.');
    }

    public function reject(Request $request, CertificateRequest $certificateRequest): RedirectResponse
    {
        $this->ensureAdministrator();
        $certificateRequest->loadMissing('achievement');

        abort_if($certificateRequest->status !== CertificateRequest::STATUS_PENDING, 422, 'Only pending requests can be rejected.');

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
    private function issuePdf(CertificateRequest $certificateRequest, AchievementCertificateService $service): void
    {
        $path = self::CERTIFICATE_FOLDER.'/'.Str::uuid().'.pdf';

        Storage::disk('private')->put($path, $service->outputPdf($certificateRequest->achievement));

        $certificateRequest->update([
            'status' => CertificateRequest::STATUS_ISSUED,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
            'certificate_path' => $path,
        ]);

        $certificateRequest->achievement->update(['certificate_path' => $path]);
    }

    /**
     * @return array<string, mixed>
     */
    private function portalData(): array
    {
        return array_merge([
            'title' => 'Certificate Requests',
            'navItems' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard'],
                ['key' => 'events', 'label' => 'Events', 'icon' => 'calendar', 'route' => 'events.index'],
                ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'admin.calendar'],
                ['key' => 'applications', 'label' => 'Applications', 'icon' => 'clipboard', 'route' => 'admin.applications'],
                ['key' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'route' => 'sports.index'],
                ['key' => 'athletes', 'label' => 'Athletes', 'icon' => 'activity', 'route' => 'athletes.index'],
                ['key' => 'medical', 'label' => 'Medical', 'icon' => 'medical', 'route' => 'admin.medical'],
                ['key' => 'coaches', 'label' => 'Coaches', 'icon' => 'users', 'route' => 'coaches.index'],
                ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'admin.attendance'],
                ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'admin.announcements'],
                ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'admin.achievements'],
                ['key' => 'certificate-requests', 'label' => 'Certificate Requests', 'icon' => 'certificate', 'route' => 'admin.certificate-requests'],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'route' => 'reports.index'],
            ],
            'active' => 'certificate-requests',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
            'announcements' => [],
        ]);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }
}