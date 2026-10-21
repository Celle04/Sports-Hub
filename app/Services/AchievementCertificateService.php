<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\CertificateRequest;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Shared logic behind the printable certificate and the downloadable PDF.
 *
 * Both the on-demand admin generator and the admin-issued certificate request
 * flow render the exact same official certificate, so the layout (and its PDF
 * quirks) live in one place.
 */
class AchievementCertificateService
{
    /**
     * Everything the certificate view needs, produced from the record itself.
     *
     * @return array<string, mixed>
     */
    public function data(Achievement $achievement, bool $forPdf = false, ?CertificateRequest $request = null): array
    {
        $achievement->loadMissing(['athlete.sport', 'sport', 'event']);
        $recipient = $achievement->recipientName();

        return [
            'achievement' => $achievement,
            'request' => $request,
            'generatedBy' => auth()->user()?->name ?? 'Sports Coordinator',
            'issuedAt' => now(),
            'recipientName' => $recipient,
            'recipientLabel' => $achievement->recipientLabel(),
            'recipientStudentId' => $achievement->isTeam() ? null : $achievement->athlete?->student_id,
            'reference' => $request?->certificateReference() ?? $this->referenceFor($achievement),
            'signatories' => $this->signatories(),
            'longRecipient' => mb_strlen($recipient) > 34,
            'logo_src' => $forPdf
                ? $this->pdfLogoPath()
                : asset('images/snnhs logo.png'),
            'use_vector_logo' => $forPdf && ! extension_loaded('gd'),
            'pdf' => $forPdf,
            'filename' => $this->filenameFor($achievement),
            'printUrl' => route('admin.achievements.certificate.print', $achievement),
            'pdfUrl' => route('admin.achievements.certificate.pdf', $achievement),
            'backUrl' => route('admin.achievements.show', $achievement),
        ];
    }

    /**
     * The certificate file name.
     */
    public function filename(Achievement $achievement): string
    {
        return $this->filenameFor($achievement);
    }

    /**
     * The certificate file name, for example
     * "certificate-achievement-2026-0001.pdf".
     */
    public function filenameFor(Achievement $achievement): string
    {
        return $achievement->certificatePdfName();
    }

    /**
     * The unique reference printed on the certificate.
     */
    public function referenceFor(Achievement $achievement): string
    {
        return $achievement->certificateReference();
    }

    /**
     * The signature blocks.
     *
     * The coordinator is the administrator who generated the certificate, so
     * that name always comes from the session. The school official stays
     * unnamed until the school authorizes a name.
     *
     * @return array<int, array{name: string|null, title: string}>
     */
    public function signatories(): array
    {
        $coordinator = config('certificate.signatories.coordinator', []);
        $official = config('certificate.signatories.official', []);

        return [
            [
                'name' => auth()->user()?->name ?? null,
                'title' => $coordinator['title'] ?? 'Sports Coordinator',
            ],
            [
                'name' => $official['name'] ?? null,
                'title' => $official['title'] ?? 'School Principal',
            ],
        ];
    }

    /**
     * The logo used inside the PDF.
     *
     * The school emblem is a 6 MB print file, which the PDF renderer has to
     * decode and scale on every certificate. A small, optimised copy of the
     * same emblem keeps the PDF fast without changing the branding.
     */
    private function pdfLogoPath(): string
    {
        $optimised = public_path('images/snnhs-logo-certificate.jpg');

        if (is_file($optimised)) {
            return $optimised;
        }

        return public_path('images/snnhs logo.png');
    }

    /**
     * The rendered PDF document as a binary string.
     */
    public function outputPdf(Achievement $achievement, ?CertificateRequest $request = null): string
    {
        return Pdf::loadView('admin.achievements.certificate', $this->data($achievement, true, $request))
            ->setPaper('a4', 'landscape')
            ->output();
    }
}
