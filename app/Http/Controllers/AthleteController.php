<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesProfilePhotos;
use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AthleteController extends Controller
{
    use HandlesProfilePhotos;

    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $athletes = User::where('role', 'Student')
            ->with(['sport.coaches', 'applications', 'medicalRecords'])
            ->withCount(['attendanceRecords', 'applications', 'medicalRecords'])
            ->athleteFilters($request->only(['search', 'sport_id', 'grade', 'gender', 'status', 'eligibility']))
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

    public function create(Application $application): View
    {
        $this->ensureAdministrator();

        return view('athletes.create', $this->portalData([
            'application' => $application->load(['sportCategory', 'athlete']),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate([
            'application_id' => ['required', 'exists:applications,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $application = Application::findOrFail($validated['application_id']);
        abort_unless($application->status === 'Approved', 422, 'Approve the application before creating an athlete account.');

        if ($application->athlete) {
            return back()->withErrors(['email' => 'An athlete account has already been created for this application.'])->withInput();
        }

        $existingAthlete = $application->student_id
            ? User::where('role', 'Student')->where('student_id', $application->student_id)->first()
            : null;

        if ($existingAthlete) {
            $application->update(['athlete_id' => $existingAthlete->id]);

            return redirect()->route('athletes.index')->with('success', 'Athlete profile already exists.');
        }

        $email = trim((string) ($validated['email'] ?? '')) ?: trim((string) $application->email);

        if ($email === '') {
            return back()->withErrors(['email' => 'This application does not have an email address. Please update the application before creating the athlete account.'])->withInput();
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'The email address on this application is not valid. Please update the application before creating the athlete account.'])->withInput();
        }

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'An account with this email already exists.'])->withInput();
        }

        $athlete = User::create([
            'name' => $application->name,
            'email' => $email,
            'username' => $validated['username'],
            'student_id' => $application->student_id,
            'sport_id' => $application->sport_id,
            'role' => 'Student',
            'password' => $validated['password'],
        ]);

        $application->update(['athlete_id' => $athlete->id]);

        return redirect()->route('athletes.index')->with('success', 'Official athlete account created.');
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

        $hasEmergencyContact = collect(ProfileController::EMERGENCY_CONTACT_FIELDS)
            ->contains(fn (string $field) => $request->filled($field));

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$athlete->id],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username,'.$athlete->id],
            'student_id' => ['nullable', 'string', 'max:100', 'unique:users,student_id,'.$athlete->id],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'status' => ['required', 'in:Active,Inactive'],
            'grade_level' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => [$hasEmergencyContact ? 'required' : 'nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => [$hasEmergencyContact ? 'required' : 'nullable', 'string', 'max:50'],
        ], $this->photoRules()), array_merge([
            'emergency_contact_name.required' => 'Enter the name of the person to contact in an emergency.',
            'emergency_contact_phone.required' => 'Enter a contact number for the emergency contact.',
        ], $this->photoMessages()));

        $athlete->fill($validated);

        $this->syncProfilePhoto($request, $athlete);

        $athlete->syncLinkedApplications();

        return redirect()->route('athletes.show', $athlete)->with('success', 'Athlete updated successfully.');
    }

    public function resetPassword(Request $request, User $athlete): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->ensureAdministrator();

        abort_unless($athlete->role === 'Student', 403);

        // An admin must never lock themselves out through the athlete action.
        abort_if($athlete->id === $request->user()->id, 403, 'You cannot reset your own account from Athlete Management.');

        $temporaryPassword = self::generateTemporaryPassword();

        $athlete->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ])->setRememberToken(Str::random(60));
        $athlete->save();

        Log::info('Administrator '.($request->user()->name ?? $request->user()->id).' reset the password for athlete '.$athlete->name.' (id '.$athlete->id.').');

        if ($request->expectsJson()) {
            return response()->json([
                'temporary_password' => $temporaryPassword,
                'athlete_name' => $athlete->name,
            ]);
        }

        return $this->redirectToPreviousAthletePage()->with('success', 'A temporary password has been generated for '.$athlete->name.'. Share it with the athlete through a non-electronic channel.');
    }

    /**
     * Generate a cryptographically secure random temporary password.
     *
     * @return string
     */
    private static function generateTemporaryPassword(): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $alphabet = $uppercase.$lowercase.$digits;

        $password = '';
        // Guarantee at least one character from each class so the result is
        // never trivially predictable.
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];

        for ($i = strlen($password); $i < 16; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return str_shuffle($password);
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

    private function eligibility(User $athlete): string
    {
        return $athlete->eligibilityStatus();
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Athlete Management',
            'navItems' => PortalNavigation::admin(),
            'active' => 'athletes',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
        ], $data);
    }

    private function redirectToPreviousAthletePage(): \Illuminate\Http\RedirectResponse
    {
        $previous = url()->previous();

        if (! is_string($previous) || $previous === '' || $previous === url('/') || $previous === route('login')) {
            $previous = route('athletes.index');
        }

        return redirect()->to($previous);
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
