<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $this->ensureAdministrator();

        return view('events.index', $this->portalData(['events' => Event::orderBy('starts_at')->get()]));
    }

    public function create(): View
    {
        $this->ensureAdministrator();

        return view('events.create', $this->portalData(['sports' => Sport::orderBy('name')->get()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        Event::create($this->validated($request));

        return redirect()->route('events.index')->with('success', 'Event created successfully.');
    }

    public function edit(Event $event): View
    {
        $this->ensureAdministrator();

        return view('events.edit', $this->portalData(['event' => $event, 'sports' => Sport::orderBy('name')->get()]));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->ensureAdministrator();

        $event->update($this->validated($request));

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->ensureAdministrator();

        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'venue' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', 'in:Scheduled,Cancelled,Completed'],
        ]);
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Event Scheduling',
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
            'active' => 'events',
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