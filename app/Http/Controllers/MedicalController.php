<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MedicalController extends Controller
{
    public function index(): View
    {
        $this->ensureAdministrator();

        $search = request('search');
        $sportId = request('sport_id');
        $status = request('status');

        $records = MedicalRecord::with(['athlete.sport'])
            ->when($search, fn ($query, $value) => $query->whereHas('athlete', fn ($athleteQuery) => $athleteQuery->where('name', 'like', "%{$value}%")))
            ->when($sportId, fn ($query, $value) => $query->whereHas('athlete', fn ($athleteQuery) => $athleteQuery->where('sport_id', $value)))
            ->when($status, fn ($query, $value) => $query->where('medical_status', $value))
            ->orderByDesc('examination_date')
            ->latest()
            ->get();

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'medical',
            'heading' => 'Medical Records',
            'subtitle' => 'Manage athlete health information and clearances',
            'athletes' => User::where('role', 'Student')->orderBy('name')->get(),
            'sports' => Sport::orderBy('name')->get(),
            'medicalRecords' => $records,
            'medicalSummary' => $this->summaryData($records),
            'medicalAlerts' => $this->clearanceAlerts($records),
            'medicalFormMode' => 'create',
            'medicalFormRecord' => null,
            'selectedMedicalRecord' => null,
            'medicalViewMode' => 'index',
            'search' => $search,
            'sportFilter' => $sportId,
            'statusFilter' => $status,
        ]));
    }

    public function create(): View
    {
        $this->ensureAdministrator();

        return $this->index();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate([
            'athlete_id' => ['required', 'exists:users,id'],
            'examination_date' => ['required', 'date'],
            'medical_status' => ['required', 'in:Pending,Cleared,Not Cleared,Restricted'],
            'next_checkup_date' => ['nullable', 'date', 'after_or_equal:examination_date'],
            'findings' => ['nullable', 'string', 'max:1000'],
            'restrictions' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'medical_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $record = MedicalRecord::create([
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
            'medical_certificate' => $this->storeCertificate($request),
        ]);

        return redirect()->route('admin.medical')->with('success', 'Medical record added successfully.');
    }

    public function show(MedicalRecord $medical): View
    {
        $this->ensureAdministrator();

        $medical->load(['athlete.sport']);

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'medical',
            'heading' => 'Medical Record',
            'subtitle' => 'Review athlete medical information and clearance status',
            'selectedMedicalRecord' => $medical,
            'medicalViewMode' => 'show',
            'medicalRecords' => MedicalRecord::with(['athlete.sport'])->latest()->get(),
            'athletes' => User::where('role', 'Student')->orderBy('name')->get(),
            'sports' => Sport::orderBy('name')->get(),
            'medicalSummary' => $this->summaryData(MedicalRecord::with(['athlete.sport'])->get()),
            'medicalAlerts' => $this->clearanceAlerts(MedicalRecord::with(['athlete.sport'])->get()),
            'medicalFormMode' => 'create',
            'medicalFormRecord' => null,
            'search' => request('search'),
            'sportFilter' => request('sport_id'),
            'statusFilter' => request('status'),
        ]));
    }

    public function edit(MedicalRecord $medical): View
    {
        $this->ensureAdministrator();

        $medical->load(['athlete.sport']);

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'medical',
            'heading' => 'Medical Records',
            'subtitle' => 'Manage athlete health information and clearances',
            'medicalRecords' => MedicalRecord::with(['athlete.sport'])->latest()->get(),
            'athletes' => User::where('role', 'Student')->orderBy('name')->get(),
            'sports' => Sport::orderBy('name')->get(),
            'medicalSummary' => $this->summaryData(MedicalRecord::with(['athlete.sport'])->get()),
            'medicalAlerts' => $this->clearanceAlerts(MedicalRecord::with(['athlete.sport'])->get()),
            'medicalFormMode' => 'edit',
            'medicalFormRecord' => $medical,
            'selectedMedicalRecord' => null,
            'medicalViewMode' => 'index',
            'search' => request('search'),
            'sportFilter' => request('sport_id'),
            'statusFilter' => request('status'),
        ]));
    }

    public function update(Request $request, MedicalRecord $medical): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate([
            'athlete_id' => ['required', 'exists:users,id'],
            'examination_date' => ['required', 'date'],
            'medical_status' => ['required', 'in:Pending,Cleared,Not Cleared,Restricted'],
            'next_checkup_date' => ['nullable', 'date', 'after_or_equal:examination_date'],
            'findings' => ['nullable', 'string', 'max:1000'],
            'restrictions' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'medical_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('medical_certificate')) {
            if ($medical->medical_certificate && Storage::disk('private')->exists($medical->medical_certificate)) {
                Storage::disk('private')->delete($medical->medical_certificate);
            }
            $validated['medical_certificate'] = $this->storeCertificate($request);
        }

        $medical->update([
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
            'medical_certificate' => $validated['medical_certificate'] ?? $medical->medical_certificate,
        ]);

        return redirect()->route('admin.medical')->with('success', 'Medical record updated successfully.');
    }

    public function destroy(MedicalRecord $medical): RedirectResponse
    {
        $this->ensureAdministrator();

        if ($medical->medical_certificate && Storage::disk('private')->exists($medical->medical_certificate)) {
            Storage::disk('private')->delete($medical->medical_certificate);
        }

        $medical->delete();

        return redirect()->route('admin.medical')->with('success', 'Medical record deleted successfully.');
    }

    public function downloadCertificate(MedicalRecord $medical)
    {
        $this->ensureAdministrator();

        abort_unless($medical->medical_certificate, 404, 'No certificate is attached to this medical record.');
        abort_unless(Storage::disk('private')->exists($medical->medical_certificate), 404, 'The requested certificate is no longer available.');

        return Storage::disk('private')->download($medical->medical_certificate, 'medical-certificate-' . $medical->id . '.' . pathinfo($medical->medical_certificate, PATHINFO_EXTENSION));
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
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }

    private function summaryData($records): array
    {
        $records = $records instanceof \Illuminate\Support\Collection ? $records : $records->get();

        return [
            'total' => $records->count(),
            'cleared' => $records->where('medical_status', 'Cleared')->count(),
            'pending' => $records->where('medical_status', 'Pending')->count(),
            'restricted' => $records->where('medical_status', 'Restricted')->count(),
            'expiring_soon' => $records->filter(fn ($record) => $record->next_checkup_date && $record->next_checkup_date->lessThanOrEqualTo(now()->addDays(30)) && $record->next_checkup_date->greaterThanOrEqualTo(now()))->count(),
            'expired' => $records->filter(fn ($record) => $record->next_checkup_date && $record->next_checkup_date->isPast())->count(),
        ];
    }

    private function clearanceAlerts($records): array
    {
        $warnings = [];
        $expiringSoon = $records->filter(fn ($record) => $record->next_checkup_date && $record->next_checkup_date->lessThanOrEqualTo(now()->addDays(30)) && $record->next_checkup_date->greaterThanOrEqualTo(now()) && $record->medical_status !== 'Not Cleared');

        if ($expiringSoon->isNotEmpty()) {
            $warnings[] = [
                'type' => 'warning',
                'title' => 'Medical clearance expiring soon',
                'message' => $expiringSoon->count() . ' athlete' . ($expiringSoon->count() > 1 ? 's have' : ' has') . ' medical clearance or next checkup dates due within 30 days.',
            ];
        }

        $expired = $records->filter(fn ($record) => $record->next_checkup_date && $record->next_checkup_date->isPast());

        if ($expired->isNotEmpty()) {
            $warnings[] = [
                'type' => 'danger',
                'title' => 'Expired medical clearance',
                'message' => $expired->count() . ' athlete' . ($expired->count() > 1 ? 's have' : ' has') . ' expired medical clearance or overdue checkup dates.',
            ];
        }

        return $warnings;
    }

    private function storeCertificate(Request $request): ?string
    {
        if (! $request->hasFile('medical_certificate')) {
            return null;
        }

        $file = $request->file('medical_certificate');
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        return $file->storeAs('medical-certificates', $fileName, 'private');
    }
}
