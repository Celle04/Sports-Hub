<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use App\Services\StudentUpdateNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $applications = Application::with(['sportCategory', 'athlete', 'reviewer'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($applicationQuery) use ($search) {
                    $applicationQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->when($request->filled('grade'), fn ($query) => $query->where('grade', $request->string('grade')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->input('date')))
            ->latest()
            ->get();

        return view('applications.index', $this->portalData([
            'page' => 'applications',
            'heading' => 'Applications Management',
            'subtitle' => 'Review and process athlete applications',
            'action' => null,
            'applications' => $applications,
            'sports' => Sport::orderBy('name')->get(),
            'grades' => Application::whereNotNull('grade')->distinct()->orderBy('grade')->pluck('grade'),
            'statuses' => self::statuses(),
            'summary' => collect(self::statuses())->mapWithKeys(fn ($status) => [$status => Application::where('status', $status)->count()])->all() + ['Total' => Application::count()],
        ]));
    }

    public function show(Application $application): View
    {
        $this->ensureAdministrator();

        return view('applications.show', $this->portalData(['application' => $application->load(['sportCategory', 'athlete', 'reviewer'])]));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $this->reviewValidated($request);
        $this->applyStatus($application, $validated['status'], $validated['review_notes'] ?? null, $validated['rejection_reason'] ?? null);

        return back()->with('success', 'Application updated successfully.');
    }

    public function approve(Application $application): RedirectResponse
    {
        $this->ensureAdministrator();
        abort_unless($this->documentsVerified($application), 422, 'Cannot approve application. Required eligibility documents are missing or have not been verified.');

        $this->applyStatus($application, 'Approved', request('review_notes'));

        return back()->with('success', 'Application approved successfully.');
    }

    public function reject(Request $request, Application $application): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000'], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        $this->applyStatus($application, 'Rejected', $validated['review_notes'] ?? null, $validated['rejection_reason']);

        return back()->with('success', 'Application rejected.');
    }

    public function requestDocuments(Request $request, Application $application): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate(['documents' => ['required', 'array', 'min:1'], 'documents.*' => ['in:medical,birth,consent'], 'message' => ['required', 'string', 'max:2000']]);
        $application->update(['status' => 'Documents Required', 'documents_requested' => $validated['documents'], 'review_notes' => $validated['message']]);
        $this->recordHistory($application, 'Additional documents requested.');
        $this->notifyApplicant($application, 'Additional documents are required for your application.');

        return back()->with('success', 'Additional documents requested.');
    }

    public function verifyDocument(Application $application, string $document): RedirectResponse
    {
        $this->ensureAdministrator();
        $definition = $this->documentDefinition($document);
        abort_unless($application->{$definition['path']}, 422, 'This document has not been submitted.');
        $application->update([$definition['status'] => 'Verified']);
        $this->recordHistory($application, $definition['label'].' verified.');
        $this->notifyApplicant($application, 'Your '.$definition['label'].' has been verified.');

        return back()->with('success', 'Document verified successfully.');
    }

    public function rejectDocument(Request $request, Application $application, string $document): RedirectResponse
    {
        $this->ensureAdministrator();
        $definition = $this->documentDefinition($document);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $notes = $application->document_rejection_notes ?? [];
        $notes[$document] = $validated['reason'];
        $application->update([$definition['status'] => 'Rejected', 'document_rejection_notes' => $notes, 'status' => 'Documents Required']);
        $this->recordHistory($application, $definition['label'].' rejected.');
        $this->notifyApplicant($application, 'Your '.$definition['label'].' needs an update: '.$validated['reason']);

        return back()->with('success', 'Document rejected.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'student_id' => ['required', 'string', 'max:100'],
            'grade' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:Male,Female,Prefer not to say'],
            'email' => ['required', 'email', 'max:255'],
            'sport_id' => ['required', 'exists:sports,id'],
            'medical_certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'birth_certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'parent_consent' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $sport = Sport::findOrFail($validated['sport_id']);
        Application::create([
            ...$validated,
            'sport' => $sport->name,
            'medical_certificate_path' => $request->file('medical_certificate')->store('application-documents/medical', 'private'),
            'birth_certificate_path' => $request->file('birth_certificate')->store('application-documents/birth', 'private'),
            'parent_consent_path' => $request->file('parent_consent')->store('application-documents/consent', 'private'),
        ]);

        return redirect()->route('application.create')->with('submitted', true);
    }

    private function reviewValidated(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::statuses())],
            'rejection_reason' => ['required_if:status,Rejected', 'nullable', 'string', 'max:2000'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $validated;
    }

    private function applyStatus(Application $application, string $status, ?string $reviewNotes = null, ?string $rejectionReason = null): void
    {
        if ($status === 'Approved') {
            abort_unless($this->documentsVerified($application), 422, 'Cannot approve application. Required eligibility documents are missing or have not been verified.');
        }

        $application->update([
            'status' => $status,
            'review_notes' => $reviewNotes ?? $application->review_notes,
            'rejection_reason' => $status === 'Rejected' ? $rejectionReason : null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $this->recordHistory($application, 'Application moved to '.$status.'.');
        $this->notifyApplicant($application, 'Your application status is now '.$status.'.');
    }

    private function notifyApplicant(Application $application, string $message): void
    {
        $student = $application->athlete;
        if (! $student && $application->student_id) {
            $student = User::query()->where('role', 'Student')->where('student_id', $application->student_id)->first();
        }

        app(StudentUpdateNotifier::class)->notifyStudent(
            $student,
            'Application update',
            $message,
            route('student.application'),
        );
    }

    private function documentsVerified(Application $application): bool
    {
        return collect(self::documentDefinitions())->every(fn ($definition, $document) => $application->{$definition['path']} && $application->{$definition['status']} === 'Verified');
    }

    private function documentDefinition(string $document): array
    {
        abort_unless(isset(self::documentDefinitions()[$document]), 404);
        return self::documentDefinitions()[$document];
    }

    private function recordHistory(Application $application, string $label): void
    {
        $history = $application->review_history ?? [];
        $history[] = ['label' => $label, 'at' => now()->toISOString(), 'by' => auth()->id()];
        $application->update(['review_history' => $history]);
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Applications Management',
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
            'active' => 'applications',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
        ], $data);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }

    public static function statuses(): array
    {
        return ['Pending', 'Under Review', 'Documents Required', 'Approved', 'Rejected', 'Waitlisted'];
    }

    public static function documentDefinitions(): array
    {
        return [
            'medical' => ['label' => 'Medical Certificate', 'path' => 'medical_certificate_path', 'status' => 'medical_document_status'],
            'birth' => ['label' => 'Birth Certificate', 'path' => 'birth_certificate_path', 'status' => 'birth_document_status'],
            'consent' => ['label' => 'Parent/Guardian Consent', 'path' => 'parent_consent_path', 'status' => 'consent_document_status'],
        ];
    }
}
