<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $coaches = Coach::with(['sport' => fn ($query) => $query->withCount(['athletes' => fn ($athletes) => $athletes->where('role', 'Student')])])
            ->withCount(['events' => fn ($query) => $query->whereIn('status', ['Scheduled', 'Ongoing'])->where('starts_at', '>=', now())])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($coachQuery) use ($search) {
                    $coachQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('specialty', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->when($request->filled('coach_type'), fn ($query) => $query->where('coach_type', $request->string('coach_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->get();

        return view('coaches.index', $this->portalData([
            'coaches' => $coaches,
            'sports' => Sport::orderBy('name')->get(),
            'coachTypes' => self::coachTypes(),
            'statuses' => self::statuses(),
            'summary' => [
                'total' => Coach::count(),
                'active' => Coach::where('status', 'Active')->count(),
                'head' => Coach::where('coach_type', 'Head Coach')->count(),
                'athletes' => \App\Models\User::where('role', 'Student')->whereHas('sport.coaches')->count(),
            ],
        ]));
    }

    public function show(Coach $coach): View
    {
        $this->ensureAdministrator();
        $coach->load([
            'sport' => fn ($query) => $query->with(['athletes' => fn ($athletes) => $athletes->where('role', 'Student')->orderBy('name')]),
            'events' => fn ($query) => $query->with('attendanceRecords')->whereIn('status', ['Scheduled', 'Ongoing'])->where('starts_at', '>=', now())->orderBy('starts_at'),
        ]);

        return view('coaches.show', $this->portalData(['coach' => $coach]));
    }

    public function create(): View
    {
        $this->ensureAdministrator();
        return view('coaches.create', $this->portalData(['sports' => Sport::where('status', 'Active')->orderBy('name')->get(), 'coachTypes' => self::coachTypes(), 'statuses' => self::statuses()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();
        Coach::create($this->validated($request));
        return redirect()->route('coaches.index')->with('success', 'Coach added successfully.');
    }

    public function edit(Coach $coach): View
    {
        $this->ensureAdministrator();
        return view('coaches.edit', $this->portalData(['coach' => $coach, 'sports' => Sport::where('status', 'Active')->orderBy('name')->get(), 'coachTypes' => self::coachTypes(), 'statuses' => self::statuses()]));
    }

    public function update(Request $request, Coach $coach): RedirectResponse
    {
        $this->ensureAdministrator();
        $coach->update($this->validated($request));
        return redirect()->route('coaches.show', $coach)->with('success', 'Coach updated successfully.');
    }

    public function destroy(Coach $coach): RedirectResponse
    {
        $this->ensureAdministrator();
        $hasRelatedRecords = $coach->events()->exists() || $coach->sport?->athletes()->where('role', 'Student')->exists();
        if ($hasRelatedRecords) {
            $coach->update(['status' => 'Inactive']);
            return redirect()->route('coaches.index')->with('success', 'This coach is assigned to existing records and was set to inactive instead of deleted.');
        }
        $coach->delete();
        return redirect()->route('coaches.index')->with('success', 'Coach deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'specialty' => ['required', 'string', 'max:255'],
            'sport_id' => ['required', 'exists:sports,id'],
            'coach_type' => ['nullable', 'in:'.implode(',', self::coachTypes())],
            'status' => ['required', 'in:'.implode(',', self::statuses())],
        ]);
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Coach Management',
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
            'active' => 'coaches',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
        ], $data);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }

    public static function coachTypes(): array
    {
        return ['Head Coach', 'Assistant Coach'];
    }

    public static function statuses(): array
    {
        return ['Active', 'Inactive'];
    }
}
