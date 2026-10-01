<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SportController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $sports = Sport::withCount(['athletes', 'coaches', 'events', 'applications'])
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

        $sport->loadCount(['athletes', 'coaches', 'events', 'applications']);
        $sport->load([
            'athletes' => fn ($query) => $query->where('role', 'Student')->orderBy('name'),
            'coaches' => fn ($query) => $query->orderBy('name'),
            'events' => fn ($query) => $query->withCount('attendanceRecords')->whereIn('status', ['Scheduled', 'Ongoing'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5),
            'applications' => fn ($query) => $query->latest()->limit(5),
        ]);

        return view('sports.show', $this->portalData(['sport' => $sport]));
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
        $uniqueName = 'unique:sports,name' . ($sport ? ',' . $sport->id : '');

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