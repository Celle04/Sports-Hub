<?php

namespace App\Http\Controllers;

use App\Models\MedicalIncident;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MedicalController extends Controller
{
    /**
     * The folder certificates are written to on the private disk.
     */
    private const CERTIFICATE_FOLDER = 'medical-certificates';

    public function index(): View
    {
        $this->ensureAdministrator();

        return view('admin.page', $this->viewData());
    }

    public function create(): View
    {
        return $this->index();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $this->validateRecord($request);

        MedicalRecord::create($this->recordAttributes(
            $validated,
            $this->storeCertificate($request),
        ));

        return redirect()->route('admin.medical')->with('success', 'Medical record added successfully.');
    }

    public function show(MedicalRecord $medical): View
    {
        $this->ensureAdministrator();

        return view('admin.page', $this->viewData([
            'heading' => 'Athlete Medical Profile',
            'subtitle' => 'Review clearance status, medical documents and injury history',
            'selectedMedicalRecord' => $medical->load([
                'athlete.sport',
                'injuries' => fn ($query) => $query->with(['sport', 'athlete.sport'])->orderByDesc('incident_date')->orderByDesc('id'),
            ]),
            'medicalProfileDocuments' => $this->profileDocuments($medical),
            'medicalViewMode' => 'show',
        ]));
    }

    public function edit(MedicalRecord $medical): View
    {
        $this->ensureAdministrator();

        return view('admin.page', $this->viewData([
            'medicalFormMode' => 'edit',
            'medicalFormRecord' => $medical,
        ]));
    }

    public function update(Request $request, MedicalRecord $medical): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $this->validateRecord($request, $medical);

        $certificate = $medical->medical_certificate;
        $uploadedCertificate = $this->storeCertificate($request);

        if ($uploadedCertificate !== null) {
            $this->deleteCertificate($medical);
            $certificate = $uploadedCertificate;
        }

        $medical->update($this->recordAttributes($validated, $certificate));

        return redirect()->route('admin.medical')->with('success', 'Medical record updated successfully.');
    }

    /**
     * Archiving keeps the student's medical history while removing the record
     * from the active list, so nothing has to be re-entered later.
     */
    public function archive(MedicalRecord $medical): RedirectResponse
    {
        $this->ensureAdministrator();

        $medical->update(['archived_at' => now()]);

        return redirect()->route('admin.medical')->with('success', 'Medical record archived. It is no longer listed among active records.');
    }

    public function restore(MedicalRecord $medical): RedirectResponse
    {
        $this->ensureAdministrator();

        $medical->update(['archived_at' => null]);

        return redirect()->route('admin.medical')->with('success', 'Medical record restored to the active list.');
    }

    public function destroy(MedicalRecord $medical): RedirectResponse
    {
        $this->ensureAdministrator();

        $this->deleteCertificate($medical);
        $medical->delete();

        return redirect()->route('admin.medical')->with('success', 'Medical record deleted successfully.');
    }

    /**
     * Certificate preview for the record profile. The file name comes from the
     * stored path, never from the request, and the file stays on the private
     * disk so it is only reachable through this authorized route.
     */
    public function viewCertificate(MedicalRecord $medical)
    {
        $this->ensureAdministrator();

        abort_unless($medical->hasCertificate(), 404, 'No certificate is attached to this medical record.');

        return Storage::disk('private')->response($medical->medical_certificate, $medical->certificateDiskName(), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function downloadCertificate(MedicalRecord $medical)
    {
        $this->ensureAdministrator();

        abort_unless($medical->hasCertificate(), 404, 'The requested certificate is no longer available.');

        return Storage::disk('private')->download($medical->medical_certificate, $medical->certificateDiskName(), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Everything every screen of the module renders with: the filtered record
     * list, the summary strip, the tracking panels and the incident log.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function viewData(array $overrides = []): array
    {
        $filters = $this->filters(request());

        $records = MedicalRecord::query()
            ->filtered($filters)
            ->with(['athlete.sport'])
            ->orderByDesc('examination_date')
            ->latest('id')
            ->get();

        $incidents = MedicalIncident::query()
            ->with(['athlete.sport', 'sport'])
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $incidentId = request('incident');
        $incidentRecord = filled($incidentId) ? MedicalIncident::with('athlete')->findOrFail($incidentId) : null;

        return array_merge($this->portalData(), [
            'page' => 'medical',
            'heading' => 'Medical Records',
            'subtitle' => 'Manage athlete health information and clearances',
            'athletes' => User::where('role', 'Student')->orderBy('name')->get(),
            'sports' => Sport::orderBy('name')->get(),
            'medicalRecords' => $records,
            'medicalSummary' => $this->summaryData($records),
            'medicalAlerts' => $this->clearanceAlerts($records),
            'expiringCertificates' => MedicalRecord::query()
                ->active()
                ->whereNotNull('next_checkup_date')
                ->whereBetween('next_checkup_date', [
                    today()->toDateString(),
                    today()->addDays($this->expiringDays())->toDateString(),
                ])
                ->with(['athlete.sport'])
                ->orderBy('next_checkup_date')
                ->orderBy('id')
                ->limit(12)
                ->get(),
            'upcomingCheckups' => MedicalRecord::query()
                ->active()
                ->upcomingCheckups()
                ->with(['athlete.sport'])
                ->orderBy('next_checkup_date')
                ->orderBy('id')
                ->limit(8)
                ->get(),
            'overdueCheckups' => MedicalRecord::query()
                ->active()
                ->overdueCheckups()
                ->with(['athlete.sport'])
                ->orderByDesc('next_checkup_date')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'medicalIncidents' => $incidents,
            'medicalIncidentFormMode' => $incidentRecord ? 'edit' : 'create',
            'medicalIncidentFormRecord' => $incidentRecord,
            'medicalFormMode' => 'create',
            'medicalFormRecord' => null,
            'selectedMedicalRecord' => null,
            'medicalProfileDocuments' => collect(),
            'medicalViewMode' => 'index',
            'medicalStatuses' => MedicalRecord::STATUSES,
            'medicalCertificateStates' => MedicalRecord::CERTIFICATE_STATES,
            'medicalSeverities' => MedicalIncident::SEVERITIES,
            'medicalClearances' => MedicalIncident::CLEARANCES,
            'medicalExpiringDays' => $this->expiringDays(),
            'search' => $filters['search'],
            'sportFilter' => $filters['sport_id'],
            'statusFilter' => $filters['status'],
            'certificateFilter' => $filters['certificate'],
            'examinationDateFilter' => $filters['examination_date'],
            'archivedFilter' => $filters['archived'],
        ], $overrides);
    }

    /**
     * Whitelisted filter values, so a hand-crafted query string can never reach
     * the query builder as a status or a broken date.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $status = $request->input('status');
        $certificate = $request->input('certificate');
        $examinationDate = $request->input('examination_date');

        if (! is_string($examinationDate) || ! Carbon::hasFormat($examinationDate, 'Y-m-d')) {
            $examinationDate = null;
        }

        return [
            'search' => trim((string) $request->input('search', '')),
            'sport_id' => $request->filled('sport_id') ? $request->input('sport_id') : null,
            'status' => is_string($status) && in_array($status, MedicalRecord::STATUSES, true) ? $status : null,
            'certificate' => is_string($certificate) && in_array($certificate, MedicalRecord::CERTIFICATE_STATES, true) ? $certificate : null,
            'examination_date' => $examinationDate,
            'archived' => $request->boolean('archived'),
        ];
    }

    /**
     * Counts behind the summary strip, taken from the records currently listed
     * so the cards always agree with the table below them.
     *
     * @param  Collection<int, MedicalRecord>  $records
     * @return array<string, int>
     */
    private function summaryData(Collection $records): array
    {
        $recoveringAthletes = MedicalIncident::query()->underRecovery()->distinct()->pluck('athlete_id');

        return [
            'total' => $records->count(),
            'cleared' => $records->where('medical_status', 'Cleared')->count(),
            'pending' => $records->where('medical_status', 'Pending')->count(),
            'restricted' => $records->where('medical_status', 'Restricted')->count(),
            'expiring_soon' => $records->filter(fn (MedicalRecord $record) => $record->isExpiringSoon())->count(),
            'under_recovery' => $records->filter(fn (MedicalRecord $record) => $recoveringAthletes->contains($record->athlete_id))->count(),
        ];
    }

    /**
     * @param  Collection<int, MedicalRecord>  $records
     * @return array<int, array<string, mixed>>
     */
    private function clearanceAlerts(Collection $records): array
    {
        $warnings = [];
        $expiringSoon = $records->filter(fn (MedicalRecord $record) => $record->isExpiringSoon() && $record->medical_status !== 'Not Cleared');

        if ($expiringSoon->isNotEmpty()) {
            $warnings[] = [
                'type' => 'warning',
                'title' => 'Medical clearance expiring soon',
                'message' => $expiringSoon->count().' athlete'.($expiringSoon->count() > 1 ? 's have' : ' has').' medical clearance or next checkup dates due within '.$this->expiringDays().' days.',
            ];
        }

        $expired = $records->filter(fn (MedicalRecord $record) => $record->isExpired());

        if ($expired->isNotEmpty()) {
            $warnings[] = [
                'type' => 'danger',
                'title' => 'Expired medical clearance',
                'message' => $expired->count().' athlete'.($expired->count() > 1 ? 's have' : ' has').' expired medical clearance or overdue checkup dates.',
            ];
        }

        return $warnings;
    }

    /**
     * Documents filed with the athlete's applications, shown beside the
     * uploaded certificate so the profile holds every medical document in one
     * place without copying files around.
     */
    private function profileDocuments(MedicalRecord $medical): Collection
    {
        $athlete = $medical->athlete;

        if (! $athlete) {
            return collect();
        }

        return $athlete->applications()
            ->whereNotNull('medical_certificate_path')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function recordAttributes(array $validated, ?string $certificate): array
    {
        return [
            'athlete_id' => $validated['athlete_id'],
            'user_id' => $validated['athlete_id'],
            'examination_date' => $validated['examination_date'],
            'medical_status' => $validated['medical_status'],
            'next_checkup_date' => $validated['next_checkup_date'] ?? null,
            'findings' => $validated['findings'] ?? null,
            'restrictions' => $validated['restrictions'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'clearance' => $validated['medical_status'],
            'last_checkup' => $validated['examination_date'],
            'medical_certificate' => $certificate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRecord(Request $request, ?MedicalRecord $medical = null): array
    {
        $mimes = implode(',', (array) config('medical.certificate_mimes'));
        $maxKilobytes = (int) config('medical.certificate_max_kb');

        return Validator::make($request->all(), [
            'athlete_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'Student')),
            ],
            'examination_date' => ['required', 'date'],
            'medical_status' => ['required', Rule::in(MedicalRecord::STATUSES)],
            'next_checkup_date' => ['nullable', 'date', 'after_or_equal:examination_date'],
            'findings' => ['nullable', 'string', 'max:1000'],
            'restrictions' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'medical_certificate' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
        ], [
            'athlete_id.required' => 'Select the athlete this medical record belongs to.',
            'athlete_id.exists' => 'The selected athlete no longer exists.',
            'examination_date.required' => 'Enter the date of the medical examination.',
            'examination_date.date' => 'Enter a valid examination date.',
            'medical_status.required' => 'Select a medical status.',
            'medical_status.in' => 'Select one of the supported medical statuses.',
            'next_checkup_date.date' => 'Enter a valid next checkup date.',
            'next_checkup_date.after_or_equal' => 'The next checkup date must be on or after the examination date.',
            'medical_certificate.mimes' => 'The medical certificate must be a PDF, JPG, JPEG or PNG file.',
            'medical_certificate.max' => 'The medical certificate may not be larger than '.round($maxKilobytes / 1024).' MB.',
        ])->validate();
    }

    /**
     * Certificates are written to the private disk under a generated name, so
     * an upload failure never leaves a half-saved record behind.
     */
    private function storeCertificate(Request $request): ?string
    {
        if (! $request->hasFile('medical_certificate')) {
            return null;
        }

        $file = $request->file('medical_certificate');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();

        $path = $file->storeAs(self::CERTIFICATE_FOLDER, Str::uuid().'.'.$extension, 'private');

        if ($path === false) {
            throw ValidationException::withMessages([
                'medical_certificate' => 'The medical certificate could not be uploaded. Please try again.',
            ]);
        }

        return $path;
    }

    private function deleteCertificate(MedicalRecord $medical): void
    {
        if ($medical->medical_certificate && Storage::disk('private')->exists($medical->medical_certificate)) {
            Storage::disk('private')->delete($medical->medical_certificate);
        }
    }

    private function expiringDays(): int
    {
        return max(1, (int) config('medical.expiring_days', 30));
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Medical Records',
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
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'route' => 'reports.index'],
            ],
            'active' => 'medical',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
            'announcements' => [],
        ], $data);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(Gate::allows('view-medical-details'), 403);
    }
}
