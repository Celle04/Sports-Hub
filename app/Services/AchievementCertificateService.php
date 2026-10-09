<?php

namespace App\Services;

use App\Models\Achievement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

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
    public function data(Achievement $achievement, bool $forPdf = false): array
    {
        $athleteName = $achievement->athlete?->name ?? 'Athlete';
        $slug = Str::slug($athleteName) ?: 'athlete';

        return [
            'achievement' => $achievement,
            'generatedBy' => auth()->user()?->name ?? 'Sports Coordinator',
            'issuedAt' => now(),
            'logo_src' => $forPdf
                ? public_path('images/snnhs logo.png')
                : asset('images/snnhs logo.png'),
            'use_vector_logo' => $forPdf && ! extension_loaded('gd'),
            'pdf' => $forPdf,
            'filename' => 'Certificate_of_Achievement_'.$slug.'_'.$achievement->id.'.pdf',
            'printUrl' => route('admin.achievements.certificate.print', $achievement),
            'pdfUrl' => route('admin.achievements.certificate.pdf', $achievement),
            'backUrl' => route('admin.achievements.show', $achievement),
        ];
    }

    public function filename(Achievement $achievement): string
    {
        return $this->data($achievement, true)['filename'];
    }

    /**
     * The rendered PDF document as a binary string.
     */
    public function outputPdf(Achievement $achievement): string
    {
        return Pdf::loadView('admin.achievements.certificate', $this->data($achievement, true))
            ->setPaper('a4', 'landscape')
            ->output();
    }
}