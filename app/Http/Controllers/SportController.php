<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($sportQuery) use ($search) {
                    $sportQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('classification', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('classification'), fn ($query) => $query->where('classification', $request->string('classification')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->get();

        return view('sports.index', $this->portalData([
            'sports' => $sports,
            'classifications' => self::classifications(),
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

        return view('sports.create', $this->portalData(['classifications' => self::classifications(), 'statuses' => self::statuses()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        Sport::create($this->validated($request));

        return redirect()->route('sports.index')->with('success', 'Sport added successfully.');
    }

    public function edit(Sport $sport): View
    {
        $this->ensureAdministrator();

        return view('sports.edit', $this->portalData(['sport' => $sport, 'classifications' => self::classifications(), 'statuses' => self::statuses()]));
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
                'coaches' => $sport->coaches()->orderBy('name')->pluck('name')->values(),
            ],
            'total_members' => $totalMembers,
            'pending_applications' => Application::query()
                ->where($forSportApplications)
                ->whereIn('status', ['Pending', 'Under Review', 'Documents Required', 'Waitlisted'])
                ->count(),
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

        $sport->update($this->validated($request, $sport));

        return redirect()->route('sports.index')->with('success', 'Sport updated successfully.');
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
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', 'in:'.implode(',', self::statuses())],
        ]);

        $validated['status'] = $validated['status'] ?? 'Active';

        return $validated;
    }

    public static function classifications(): array
    {
        return ['Team Sport', 'Individual', 'Racket Sport', 'Athletics', 'Mind Sport', 'Combat Sport', 'Other'];
    }

    public static function statuses(): array
    {
        return ['Active', 'Inactive'];
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Sports Management',
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
