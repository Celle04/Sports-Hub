<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AthleteController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $athletes = User::where('role', 'Student')
            ->with(['sport.coaches', 'applications', 'medicalRecords'])
            ->withCount(['attendanceRecords', 'applications', 'medicalRecords'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($athleteQuery) use ($search) {
                    $athleteQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->when($request->filled('grade'), fn ($query) => $query->whereHas('applications', fn ($applications) => $applications->where('grade', $request->string('grade')->toString())))
            ->when($request->filled('gender'), fn ($query) => $query->whereHas('applications', fn ($applications) => $applications->where('gender', $request->string('gender')->toString())))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('eligibility'), fn ($query) => $this->applyEligibilityFilter($query, $request->string('eligibility')->toString()))
            ->orderBy('name')
            ->get();

        return view('athletes.index', $this->portalData([
            'athletes' => $athletes,
            'sports' => Sport::with('coaches')->orderBy('name')->get(),
            'grades' => \App\Models\Application::whereNotNull('grade')->distinct()->orderBy('grade')->pluck('grade'),
            'genders' => \App\Models\Application::whereNotNull('gender')->distinct()->orderBy('gender')->pluck('gender'),
            'statuses' => ['Active', 'Inactive'],
            'eligibilities' => ['Eligible', 'Pending', 'Not Eligible'],
            'summary' => [
                'total' => User::where('role', 'Student')->count(),
                'active' => User::where('role', 'Student')->where('status', 'Active')->count(),
                'team' => User::where('role', 'Student')->whereHas('sport', fn ($query) => $query->where('classification', 'Team Sport'))->count(),
                'individual' => User::where('role', 'Student')->whereHas('sport', fn ($query) => $query->where('classification', 'Individual'))->count(),
            ],
        ]));
    }

    public function show(User $athlete): View
    {
        $this->ensureAthlete($athlete);
        $athlete->load(['sport.coaches', 'applications.sportCategory', 'medicalRecords', 'attendanceRecords.event.sport']);

        return view('athletes.show', $this->portalData(['athlete' => $athlete]));
    }

    public function edit(User $athlete): View
    {
        $this->ensureAthlete($athlete);

        return view('athletes.edit', $this->portalData(['athlete' => $athlete, 'sports' => Sport::orderBy('name')->get(), 'statuses' => ['Active', 'Inactive']]));
    }

    public function update(Request $request, User $athlete): RedirectResponse
    {
        $this->ensureAthlete($athlete);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$athlete->id],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username,'.$athlete->id],
            'student_id' => ['nullable', 'string', 'max:100', 'unique:users,student_id,'.$athlete->id],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);
        $athlete->update($validated);

        return redirect()->route('athletes.show', $athlete)->with('success', 'Athlete updated successfully.');
    }

    public function destroy(User $athlete): RedirectResponse
    {
        $this->ensureAthlete($athlete);
        $hasRelatedRecords = $athlete->applications()->exists() || $athlete->attendanceRecords()->exists() || $athlete->medicalRecords()->exists();

        if ($hasRelatedRecords) {
            $athlete->update(['status' => 'Inactive']);
            return redirect()->route('athletes.index')->with('success', 'This athlete has related records and was set to inactive instead of deleted.');
        }

        $athlete->delete();
        return redirect()->route('athletes.index')->with('success', 'Athlete deleted successfully.');
    }

    private function applyEligibilityFilter($query, string $eligibility)
    {
        return match ($eligibility) {
            'Eligible' => $query->whereHas('medicalRecords', fn ($medical) => $medical->where('medical_status', 'Cleared')),
            'Not Eligible' => $query->where(function ($notEligible) {
                $notEligible->whereHas('medicalRecords', fn ($medical) => $medical->whereIn('medical_status', ['Not Cleared', 'Restricted']))
                    ->orWhereHas('applications', fn ($applications) => $applications->where('status', 'Rejected'));
            }),
            default => $query->where(function ($pending) {
                $pending->whereDoesntHave('medicalRecords', fn ($medical) => $medical->where('medical_status', 'Cleared'))
                    ->whereDoesntHave('applications', fn ($applications) => $applications->where('status', 'Rejected'));
            }),
        };
    }

    private function eligibility(User $athlete): string
    {
        if ($athlete->medicalRecords->contains(fn ($record) => $record->medical_status === 'Cleared')) {
            return 'Eligible';
        }
        if ($athlete->applications->contains(fn ($application) => $application->status === 'Rejected')) {
            return 'Not Eligible';
        }
        return 'Pending';
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Athlete Management',
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
            'active' => 'athletes',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
        ], $data);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }

    private function ensureAthlete(User $athlete): void
    {
        $this->ensureAdministrator();
        abort_unless($athlete->role === 'Student', 404);
    }
}
