<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SportController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $sports = Sport::withCount([
            'athletes' => fn ($query) => $query->where('role', 'Student'),
            'coaches',
            'events',
            'applications',
        ])
            ->with('coaches')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($sportQuery) use ($search) {
                    $sportQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('classification', 'like', "%{$search}%")
                        ->orWhere('gender', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('classification'), fn ($query) => $query->where('classification', $request->string('classification')->toString()))
            ->when($request->filled('gender'), fn ($query) => $query->where('gender', $request->string('gender')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->get();

        return view('sports.index', $this->portalData([
            'sports' => $sports,
            'classifications' => self::classifications(),
            'genders' => self::genders(),
            'statuses' => self::statuses(),
            'summary' => [
                'total' => Sport::count(),
                'team' => Sport::where('classification', 'Team Sport')->count(),
                'individual' => Sport::where('classification', 'Individual')->count(),
                'active' => Sport::where('status', 'Active')->count(),
            ],
        ]));
    }

    public function create(): View
    {
        $this->ensureAdministrator();

        return view('sports.create', $this->portalData([
            'classifications' => self::classifications(),
            'genders' => self::genders(),
            'statuses' => self::statuses(),
            'coaches' => $this->coachOptions(),
            'assignedCoachId' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $data = $this->validated($request);
        $sport = new Sport($data);
        $sport->save();

        // handle logo if present
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('sports', 'public');
            $sport->logo_path = $path;
            $sport->save();
        }

        $message = 'Sport added successfully.';
        $note = $this->syncCoachAssignment($sport, $this->requestedCoachId($request));

        if ($note) {
            $message .= ' '.$note;
        }

        return redirect()->route('sports.index')->with('success', $message);
    }

    public function edit(Sport $sport): View
    {
        $this->ensureAdministrator();

        $sport->load('coaches');

        return view('sports.edit', $this->portalData([
            'sport' => $sport,
            'classifications' => self::classifications(),
            'genders' => self::genders(),
            'statuses' => self::statuses(),
            'coaches' => $this->coachOptions(),
            'assignedCoachId' => $sport->assignedCoach()?->id,
        ]));
    }

    public function show(Sport $sport): View
    {
        $this->ensureAdministrator();

        $sport->loadCount([
            'athletes' => fn ($query) => $query->where('role', 'Student'),
            'coaches',
            'events',
            'applications',
        ]);
        $sport->load([
            'athletes' => fn ($query) => $query->where('role', 'Student')->orderBy('name'),
            'coaches' => fn ($query) => $query->orderBy('name'),
            'events' => fn ($query) => $query->withCount('attendanceRecords')->whereIn('status', ['Scheduled', 'Ongoing'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5),
            'applications' => fn ($query) => $query->latest()->limit(5),
        ]);

        return view('sports.show', $this->portalData(['sport' => $sport]));
    }

    public function studentMembers(Request $request, Sport $sport): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'Student', 403);
        abort_unless($sport->status === 'Active', 404);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Approved,Active'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $forSportApplications = static fn ($query) => $query->where(fn ($sportApplications) => $sportApplications
            ->where('sport_id', $sport->id)
            ->orWhere(fn ($legacyApplications) => $legacyApplications
                ->whereNull('sport_id')
                ->where('sport', $sport->name)));

        $members = User::query()
            ->where('role', 'Student')
            ->where('status', 'Active')
            ->where('sport_id', $sport->id)
            ->whereDoesntHave('applications', fn ($query) => $forSportApplications($query)->where('status', '!=', 'Approved'))
            ->with(['applications' => fn ($query) => $forSportApplications($query)
                ->where('status', 'Approved')
                ->latest('id')
                ->limit(1)])
            ->orderBy('name')
            ->orderBy('id');

        $totalMembers = (clone $members)->count();

        $members
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $search = trim($validated['search']);
                $query->where(function ($studentQuery) use ($search) {
                    $studentQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                });
            })
            ->when(! empty($validated['grade']), fn ($query) => $query->whereHas('applications', fn ($applications) => $forSportApplications($applications)
                ->where('status', 'Approved')
                ->where('grade', $validated['grade'])))
            ->when(($validated['status'] ?? null) === 'Approved', fn ($query) => $query->whereHas('applications', fn ($applications) => $forSportApplications($applications)
                ->where('status', 'Approved')))
            ->when(($validated['status'] ?? null) === 'Active', fn ($query) => $query->whereDoesntHave('applications', fn ($applications) => $forSportApplications($applications)
                ->where('status', 'Approved')));

        $page = $members->paginate(10)->withQueryString();
        $roster = $page->getCollection()->map(fn (User $student) => [
            'id' => $student->id,
            'name' => $student->name,
            'student_id' => $student->student_id,
            'grade' => $student->applications->first()?->grade,
            'photo_url' => $student->profile_photo_path
                ? Storage::disk('public')->url($student->profile_photo_path)
                : null,
            'enrollment_status' => $student->applications->isNotEmpty() ? 'Approved' : 'Active',
            'profile_url' => route('student.sports.member-profile', [$sport, $student]),
        ]);

        return response()->json([
            'program' => [
                'name' => $sport->name,
                'description' => $sport->description,
                'classification' => $sport->classification,
                'gender' => $sport->gender,
                'coaches' => $sport->coaches()->orderBy('name')->get()->map(fn ($coach) => [
                    'name' => $coach->name,
                    'specialty' => $coach->specialty,
                    'coach_type' => $coach->coach_type,
                    'email' => $coach->email,
                    'phone' => $coach->phone,
                    'photo_url' => $coach->profile_picture_path
                        ? Storage::disk('public')->url($coach->profile_picture_path)
                        : null,
                ])->values(),
            ],
            'total_members' => $totalMembers,
            'grades' => Application::query()
                ->where($forSportApplications)
                ->where('status', 'Approved')
                ->whereNotNull('grade')
                ->whereHas('athlete', fn ($athlete) => $athlete
                    ->where('role', 'Student')
                    ->where('status', 'Active')
                    ->where('sport_id', $sport->id))
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade')
                ->values(),
            'members' => [
                'data' => $roster->values(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function studentMemberProfile(Sport $sport, User $athlete): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'Student', 403);
        abort_unless($sport->status === 'Active', 404);

        $forSportApplications = static fn ($query) => $query->where(fn ($sportApplications) => $sportApplications
            ->where('sport_id', $sport->id)
            ->orWhere(fn ($legacyApplications) => $legacyApplications
                ->whereNull('sport_id')
                ->where('sport', $sport->name)));

        abort_unless(
            $athlete->role === 'Student'
                && $athlete->status === 'Active'
                && (int) $athlete->sport_id === (int) $sport->id
                && ! $athlete->applications()
                    ->where($forSportApplications)
                    ->where('status', '!=', 'Approved')
                    ->exists(),
            404,
        );

        $approvedApplication = $athlete->applications()
            ->where($forSportApplications)
            ->where('status', 'Approved')
            ->latest('id')
            ->first();

        return response()->json([
            'profile' => [
                'name' => $athlete->name,
                'student_id' => $athlete->student_id,
                'grade' => $approvedApplication?->grade,
                'photo_url' => $athlete->profile_photo_path
                    ? Storage::disk('public')->url($athlete->profile_photo_path)
                    : null,
                'status' => $athlete->status,
                'sport' => $sport->name,
                'coaches' => $sport->coaches()->orderBy('name')->pluck('name')->values(),
                'date_joined' => $approvedApplication?->reviewed_at?->format('F j, Y'),
            ],
        ]);
    }

    public function update(Request $request, Sport $sport): RedirectResponse
    {
        $this->ensureAdministrator();

        $previousCoachId = $sport->assignedCoach()?->id;

        $data = $this->validated($request, $sport);
        $sport->update($data);

        $previousLogo = $sport->logo_path;

        if ($request->boolean('remove_logo') && ! $request->hasFile('logo')) {
            $sport->logo_path = null;
            $sport->save();
            if ($previousLogo && str_starts_with($previousLogo, 'sports/')) {
                Storage::disk('public')->delete($previousLogo);
            }
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('sports', 'public');
            $sport->logo_path = $path;
            $sport->save();
            if ($previousLogo && str_starts_with($previousLogo, 'sports/')) {
                Storage::disk('public')->delete($previousLogo);
            }
        }

        $message = 'Sport updated successfully.';
        $note = $this->syncCoachAssignment($sport, $this->requestedCoachId($request), $previousCoachId);

        if ($note) {
            $message .= ' '.$note;
        }

        return redirect()->route('sports.index')->with('success', $message);
    }

    public function destroy(Sport $sport): RedirectResponse
    {
        $this->ensureAdministrator();

        $hasRelatedRecords = $sport->athletes()->exists() || $sport->coaches()->exists() || $sport->events()->exists() || $sport->applications()->exists();

        if ($hasRelatedRecords) {
            $sport->update(['status' => 'Inactive']);

            return redirect()->route('sports.index')->with('success', 'This sport is in use and was set to inactive instead of deleted.');
        }

        $sport->delete();

        return redirect()->route('sports.index')->with('success', 'Sport deleted successfully.');
    }

    private function validated(Request $request, ?Sport $sport = null): array
    {
        $uniqueName = 'unique:sports,name'.($sport ? ','.$sport->id : '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'classification' => ['required', 'in:'.implode(',', self::classifications())],
            'gender' => ['nullable', 'in:'.implode(',', self::genders())],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', 'in:'.implode(',', self::statuses())],
            'coach_id' => ['nullable', 'integer', 'exists:coaches,id'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'coach_id.exists' => 'The selected coach is invalid. Please choose a coach from the list.',
            'coach_id.integer' => 'The selected coach is invalid. Please choose a coach from the list.',
        ]);

        $validated['status'] = $validated['status'] ?? 'Active';
        $validated['gender'] = $validated['gender'] ?? 'Boys';

        unset($validated['logo'], $validated['remove_logo'], $validated['coach_id']);

        return $validated;
    }

    /**
     * The coach the administrator picked on the sport form, if any.
     */
    private function requestedCoachId(Request $request): ?int
    {
        $coachId = $request->input('coach_id');

        return filled($coachId) ? (int) $coachId : null;
    }

    /**
     * Coaches that can be assigned to a sport, listed with the sport or
     * specialty they are currently recorded against.
     *
     * @return Collection<int, Coach>
     */
    private function coachOptions(): Collection
    {
        return Coach::with('sport')
            ->orderBy('name')
            ->get();
    }

    /**
     * Keep the sport's coach assignment in step with the submitted selection.
     *
     * The coach-to-sport link lives on `coaches.sport_id`, so a coach belongs
     * to a single sport while a sport may hold several coaches. Selecting a
     * coach that is already recorded under another sport moves them here, and
     * the coach previously held by this sport is only detached when the
     * selection really changes. Coach records are never deleted.
     */
    private function syncCoachAssignment(Sport $sport, ?int $coachId, ?int $previousCoachId = null): ?string
    {
        if ($coachId === $previousCoachId) {
            return null;
        }

        $notes = [];

        if ($previousCoachId) {
            $previousCoach = Coach::find($previousCoachId);

            if ($previousCoach && (int) $previousCoach->sport_id === (int) $sport->id) {
                $previousCoach->update(['sport_id' => null]);
                $notes[] = sprintf('%s is no longer assigned to %s.', $previousCoach->name, $sport->name);
            }
        }

        if (! $coachId) {
            return $notes ? implode(' ', $notes) : null;
        }

        $coach = Coach::findOrFail($coachId);
        $previousSport = $coach->sport;

        $coach->update(['sport_id' => $sport->id]);

        $notes[] = sprintf('%s is now assigned to %s.', $coach->name, $sport->name);

        if ($previousSport && (int) $previousSport->id !== (int) $sport->id) {
            $notes[] = sprintf('They were moved from %s.', $previousSport->name);
        }

        return implode(' ', $notes);
    }

    public static function classifications(): array
    {
        return ['Team Sport', 'Individual', 'Racket Sport', 'Athletics', 'Mind Sport', 'Combat Sport', 'Other'];
    }

    public static function genders(): array
    {
        return ['Boys', 'Girls'];
    }

    public static function statuses(): array
    {
        return ['Active', 'Inactive'];
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Sports Management',
            'navItems' => PortalNavigation::admin(),
            'active' => 'sports',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
        ], $data);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }
}
