<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Coach;
use App\Models\Sport;
use App\Services\StudentUpdateNotifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $eventsQuery = Event::with(['sport', 'coach'])->withCount('attendanceRecords')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($eventQuery) use ($search) {
                    $eventQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('coach', fn ($coachQuery) => $coachQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('event_type'), fn ($query) => $query->where('event_type', $request->string('event_type')->toString()))
            ->when($request->filled('venue'), fn ($query) => $query->where('venue', $request->string('venue')->toString()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('starts_at', $request->input('date')));

        $events = $eventsQuery->orderBy('starts_at')->get();

        return view('events.index', $this->portalData([
            'events' => $events,
            'sports' => Sport::orderBy('name')->get(),
            'coaches' => Coach::orderBy('name')->get(),
            'venues' => Event::query()->whereNotNull('venue')->distinct()->orderBy('venue')->pluck('venue'),
            'eventTypes' => self::eventTypes(),
            'statuses' => self::statuses(),
            'summary' => [
                'total' => Event::count(),
                'scheduled' => Event::where('status', 'Scheduled')->count(),
                'ongoing' => Event::where('status', 'Ongoing')->count(),
                'completed' => Event::where('status', 'Completed')->count(),
                'cancelled' => Event::where('status', 'Cancelled')->count(),
            ],
            'upcomingEvents' => $events->filter(fn ($event) => in_array($event->status, ['Scheduled', 'Ongoing'], true) && $event->starts_at->greaterThanOrEqualTo(now()))->take(5)->values(),
        ]));
    }

    public function create(): View
    {
        $this->ensureAdministrator();

        return view('events.create', $this->portalData(['sports' => Sport::orderBy('name')->get(), 'coaches' => Coach::orderBy('name')->get(), 'eventTypes' => self::eventTypes(), 'statuses' => self::statuses()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $this->validated($request);
        $this->ensureVenueAvailable($validated['venue'], $validated['starts_at'], $validated['ends_at']);
        $event = Event::create($validated);

        if ($event->status === 'Scheduled' && $event->starts_at?->isFuture()) {
            app(StudentUpdateNotifier::class)->notifyStudents(
                $event->sport_id ? [$event->sport_id] : null,
                'New sports event',
                $event->title.' has been scheduled for '.$event->starts_at->format('M j, Y g:i A').'.',
                route('student.schedule'),
            );
        }

        return redirect()->route('events.index')->with('success', 'Event created successfully.');
    }

    public function edit(Event $event): View
    {
        $this->ensureAdministrator();

        return view('events.edit', $this->portalData(['event' => $event, 'sports' => Sport::orderBy('name')->get(), 'coaches' => Coach::orderBy('name')->get(), 'eventTypes' => self::eventTypes(), 'statuses' => self::statuses()]));
    }

    public function show(Event $event): View
    {
        $this->ensureAdministrator();

        return view('events.show', $this->portalData(['event' => $event->load(['sport', 'coach'])->loadCount('attendanceRecords')]));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->ensureAdministrator();

        $previousStatus = $event->status;
        $previousSportId = $event->sport_id;
        $validated = $this->validated($request);
        $this->ensureVenueAvailable($validated['venue'], $validated['starts_at'], $validated['ends_at'], $event);
        $event->update($validated);

        if (in_array($previousStatus, ['Scheduled', 'Ongoing'], true) || $event->status === 'Scheduled') {
            $eventChanges = ['title', 'sport_id', 'venue', 'starts_at', 'ends_at', 'status', 'coach_id', 'description'];

            if ($event->wasChanged($eventChanges)) {
                $sportIds = $previousSportId === null || $event->sport_id === null
                    ? null
                    : [$previousSportId, $event->sport_id];
                $updateMessage = match ($event->status) {
                    'Cancelled' => $event->title.' has been cancelled.',
                    'Postponed' => $event->title.' has been postponed.',
                    default => $event->title.' has been updated. Check the schedule for details.',
                };

                app(StudentUpdateNotifier::class)->notifyStudents(
                    $sportIds,
                    'Sports event update',
                    $updateMessage,
                    route('student.schedule'),
                );
            }
        }

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->ensureAdministrator();

        if ($event->status === 'Scheduled' && $event->starts_at?->isFuture()) {
            app(StudentUpdateNotifier::class)->notifyStudents(
                $event->sport_id ? [$event->sport_id] : null,
                'Sports event removed',
                $event->title.' has been removed from the schedule.',
                route('student.schedule'),
            );
        }

        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'venue' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'event_type' => ['nullable', 'in:'.implode(',', self::eventTypes())],
            'coach_id' => ['nullable', 'exists:coaches,id'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:'.implode(',', self::statuses())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['event_type'] = $validated['event_type'] ?? 'Other';

        return $validated;
    }

    private function ensureVenueAvailable(string $venue, string $startsAt, string $endsAt, ?Event $currentEvent = null): void
    {
        $conflict = Event::whereRaw('LOWER(venue) = ?', [strtolower(trim($venue))])
            ->where('starts_at', '<', Carbon::parse($endsAt))
            ->where('ends_at', '>', Carbon::parse($startsAt))
            ->when($currentEvent, fn ($query) => $query->where($query->getModel()->getKeyName(), '!=', $currentEvent->getKey()))
            ->first();

        if ($conflict) {
            request()->validate(['venue' => [function ($attribute, $value, $fail) use ($conflict) {
                $fail("Venue conflict: {$value} is already reserved from {$conflict->starts_at->format('g:i A')} to {$conflict->ends_at->format('g:i A')} on this date.");
            }]]);
        }
    }

    public static function eventTypes(): array
    {
        return ['Training', 'Practice', 'Tournament', 'Competition', 'Tryout', 'Friendly Match', 'District Meet', 'Regional Meet', 'Meeting', 'Other'];
    }

    public static function statuses(): array
    {
        return ['Scheduled', 'Ongoing', 'Completed', 'Cancelled', 'Postponed'];
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