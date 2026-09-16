<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SportController extends Controller
{
    public function index(): View
    {
        $this->ensureAdministrator();

        return view('sports.index', $this->portalData(['sports' => Sport::latest()->get()]));
    }

    public function create(): View
    {
        $this->ensureAdministrator();

        return view('sports.create', $this->portalData());
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

        return view('sports.edit', $this->portalData(['sport' => $sport]));
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

        $sport->delete();

        return redirect()->route('sports.index')->with('success', 'Sport deleted successfully.');
    }

    private function validated(Request $request, ?Sport $sport = null): array
    {
        $uniqueName = 'unique:sports,name' . ($sport ? ',' . $sport->id : '');

        return $request->validate([
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'classification' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
        ]);
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