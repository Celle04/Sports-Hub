<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use App\Services\AttendanceSessionService;
use App\Services\StudentUpdateNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceSessionService $attendance)
    {
    }

    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        $filters = $this->filters($request);

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'attendance',
            'heading' => 'Attendance Management',
            'subtitle' => 'Track athlete attendance by sports activity',
            'sports' => Sport::query()
                ->withCount(['athletes' => fn ($query) => $query->where('role', 'Student')])
                ->orderBy('name')
                ->get(),
            'sessionStatuses' => config('attendance.session_statuses'),
            'recordStatuses' => config('attendance.record_statuses'),
            'filters' => $filters,
            'summary' => $this->summary($filters['date'] ?? today()->toDateString()),
            'attendanceSessions' => $this->sessions($filters),
            'selectedAttendanceSession' => $this->selectedSession($request),
            'attendanceSessionRecords' => $this->sessionRecords($request),
            'editingAttendance' => $request->filled('edit_id')
                ? Attendance::query()->with(['athlete.sport', 'session.sport'])->find($request->integer('edit_id'))
                : null,
            'attendance' => $this->attendanceHistory($filters),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_id' => ['nullable', 'exists:events,id'],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'session_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time', 'required_with:start_time'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'venue' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:Open,Closed,Cancelled'],
        ]);

        $sportRequired = $request->boolean('sport_required');

        if ($sportRequired && ! $validated['sport_id']) {
            return back()->withErrors(['sport_id' => 'Select the sport this attendance session belongs to.'])->withInput();
        }

        $event = ! empty($validated['event_id']) ? Event::findOrFail($validated['event_id']) : null;
        $validated['sport_id'] = $validated['sport_id'] ?? $event?->sport_id;

        $status = $validated['status'] ?? 'Open';

        $session = AttendanceSession::create([
            'title' => $validated['title'],
            'event_id' => $event?->id,
            'sport_id' => $validated['sport_id'],
            'session_date' => $validated['session_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'late_grace_minutes' => $validated['late_grace_minutes'] ?? config('attendance.late_grace_minutes'),
            'venue' => $validated['venue'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'created_by' => auth()->id(),
            'opened_at' => $status === 'Open' ? now() : null,
            'closed_at' => in_array($status, ['Closed', 'Cancelled'], true) ? now() : null,
        ]);

        $rosterSize = $this->attendance->syncRoster($session);

        $message = $rosterSize > 0
            ? "Attendance session created. {$rosterSize} athlete(s) assigned to {$session->effectiveSportName()} were added as Pending."
            : 'Attendance session created. No active athletes are currently assigned to that sport.';

        return redirect()
            ->route('admin.attendance', ['session_id' => $session->id])
            ->with('success', $message);
    }

    public function open(AttendanceSession $session): RedirectResponse
    {
        $this->ensureAdministrator();

        $this->attendance->open($session);

        return back()->with('success', 'Attendance is now open. Eligible athletes can check in.');
    }

    public function close(AttendanceSession $session): RedirectResponse
    {
        $this->ensureAdministrator();

        $this->attendance->close($session);

        return back()->with('success', 'Attendance session closed. Athletes can no longer check in.');
    }

    public function cancel(AttendanceSession $session): RedirectResponse
    {
        $this->ensureAdministrator();

        $this->attendance->cancel($session);

        return back()->with('success', 'Attendance session cancelled. It will not count towards attendance rates.');
    }

    public function sync(AttendanceSession $session): RedirectResponse
    {
        $this->ensureAdministrator();

        $this->attendance->pruneRoster($session);
        $created = $this->attendance->syncRoster($session);

        return back()->with('success', $created > 0
            ? "Roster refreshed. {$created} athlete(s) added as Pending."
            : 'Roster refreshed. It already matches the current sport assignment.');
    }

    public function destroy(AttendanceSession $session): RedirectResponse
    {
        $this->ensureAdministrator();

        $session->delete();

        return redirect()->route('admin.attendance')->with('success', 'Attendance session deleted.');
    }

    public function updateRecord(Request $request, Attendance $attendance): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $request->validate([
            'status' => ['required', 'in:Present,Late,Absent,Excused,Pending'],
            'attended_on' => ['nullable', 'date'],
        ]);

        $previousStatus = $attendance->status;
        $attendance->update(array_filter([
            'status' => $validated['status'],
            'attended_on' => $validated['attended_on'] ?? $attendance->attended_on,
        ], fn ($value) => $value !== null));

        if ($attendance->wasChanged('status')) {
            $activityName = $attendance->session?->displayName() ?? $attendance->event?->title ?? 'your activity';
            app(StudentUpdateNotifier::class)->notifyStudent(
                $attendance->athlete,
                'Attendance updated',
                'Your attendance for '.$activityName.' was changed from '.$previousStatus.' to '.$attendance->status.'.',
                route('student.attendance'),
            );
        }

        return redirect()
            ->route('admin.attendance', ['session_id' => $attendance->attendance_session_id])
            ->with('success', 'Attendance status updated.');
    }

    public function export(Request $request, AttendanceSession $session): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureAdministrator();

        $records = Attendance::query()
            ->with(['athlete', 'session'])
            ->forSession($session->id)
            ->orderBy('user_id')
            ->get()
            ->sortBy(fn (Attendance $record) => $record->athlete?->name ?? '')
            ->values();

        $label = preg_replace('#[^A-Za-z0-9]+#', '_', $session->displayName());
        $date = $session->session_date?->format('Y-m-d');

        // Build the CSV into a temp stream so very large rosters are streamed
        // rather than concatenated, then return it as a downloadable file.
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM so Microsoft Excel recognizes the encoding.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['SNNHS SportsHub - Attendance Session Report']);
        fputcsv($handle, ['Generated', now()->format('F j, Y g:i A')]);
        fputcsv($handle, ['Generated By', auth()->user()?->name ?? 'Administrator']);
        fputcsv($handle, ['Session', $session->displayName()]);
        fputcsv($handle, ['Sport', $session->effectiveSportName()]);
        fputcsv($handle, ['Date', $session->session_date?->toDateString()]);
        fputcsv($handle, ['Time', $session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time)->format('H:i').($session->end_time ? ' - '.\Illuminate\Support\Carbon::parse($session->end_time)->format('H:i') : '') : '']);
        fputcsv($handle, ['Venue', $session->venue]);
        fputcsv($handle, ['Status', $session->status]);
        fputcsv($handle, []);
        fputcsv($handle, ['No.', 'Student ID', 'Athlete Name', 'Sport', 'Team', 'Attendance Status', 'Time In', 'Remarks']);

        $number = 0;
        foreach ($records as $record) {
            $number++;
            fputcsv($handle, [
                $number,
                $this->csvCell($record->athlete?->student_id ?? ''),
                $this->csvCell($record->athlete?->name ?? ''),
                $this->csvCell($record->athlete?->sport?->name ?? $session->effectiveSportName()),
                $this->csvCell(''), // Team: attendance records are not tied to a named team
                $this->csvCell($record->status),
                $this->csvCell($record->check_in_time?->format('Y-m-d H:i:s') ?? ''),
                $this->csvCell(''), // Remarks: no remarks field on attendance records
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="SNNHS_Attendance_'.$label.'_'.$date.'.csv"',
        ]);
    }

    /**
     * Sanitize a CSV cell to protect against formula injection. Cells that
     * begin with a formula character are prefixed with a tab so spreadsheet
     * applications treat the value as literal text instead of executing it.
     *
     * @param  string  $value
     * @return string
     */
    private function csvCell(string $value): string
    {
        if (preg_match('#^[=+\-@\t\r\n]#', $value)) {
            return "\t".$value;
        }

        return $value;
    }

    public function exportPdf(AttendanceSession $session): Response
    {
        $this->ensureAdministrator();

        $records = Attendance::query()
            ->with(['athlete.sport', 'session.sport'])
            ->forSession($session->id)
            ->orderBy('user_id')
            ->get()
            ->sortBy(fn (Attendance $record) => $record->athlete?->name ?? '')
            ->values();

        $total = $records->count();
        $present = $records->where('status', 'Present')->count();
        $absent = $records->where('status', 'Absent')->count();
        $recorded = $records->whereIn('status', ['Present', 'Late', 'Absent', 'Excused'])->count();
        $pending = $records->where('status', 'Pending')->count();
        $unrecorded = max(0, $total - $recorded);

        $pdf = Pdf::loadView('admin.attendance.export.pdf', [
            'session' => $session,
            'records' => $records,
            'totals' => [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'pending_unrecorded' => $pending + $unrecorded,
                'late' => $records->where('status', 'Late')->count(),
                'excused' => $records->where('status', 'Excused')->count(),
            ],
            'generatedAt' => now(),
            'generatedBy' => auth()->user()?->name ?? 'Administrator',
        ])->setPaper('a4', 'portrait');

        $label = preg_replace('#[^A-Za-z0-9]+#', '_', $session->displayName());
        $date = $session->session_date?->format('Y-m-d');

        return $pdf->download('SNNHS_Attendance_'.$label.'_'.$date.'.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:Open,Closed,Cancelled'],
            'record_status' => ['nullable', 'in:Pending,Present,Late,Absent,Excused'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['search'] = trim($validated['search'] ?? '');

        return $validated;
    }

    private function sessions(array $filters): Collection
    {
        return AttendanceSession::query()
            ->with(['sport', 'event.sport'])
            ->withCount([
                'attendanceRecords as total_count',
                'attendanceRecords as present_count' => fn ($query) => $query->where('status', 'Present'),
                'attendanceRecords as late_count' => fn ($query) => $query->where('status', 'Late'),
                'attendanceRecords as pending_count' => fn ($query) => $query->where('status', 'Pending'),
                'attendanceRecords as absent_count' => fn ($query) => $query->where('status', 'Absent'),
                'attendanceRecords as excused_count' => fn ($query) => $query->where('status', 'Excused'),
            ])
            ->forSport($filters['sport_id'] ?? null)
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('session_date', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['record_status'] ?? null, fn ($query, $status) => $query->whereHas(
                'attendanceRecords',
                fn ($recordQuery) => $recordQuery->where('status', $status)
            ))
            ->when($filters['search'] !== '', fn ($query) => $query->where(function (Builder $nested) use ($filters) {
                $search = "%{$filters['search']}%";
                $nested->where('title', 'like', $search)
                    ->orWhere('venue', 'like', $search)
                    ->orWhereHas('attendanceRecords.athlete', fn (Builder $athleteQuery) => $athleteQuery
                        ->where('name', 'like', $search)
                        ->orWhere('student_id', 'like', $search));
            }))
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get();
    }

    private function attendanceHistory(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Attendance::query()
            ->with(['athlete.sport', 'session.sport', 'event.sport'])
            ->when($filters['search'] !== '', fn ($query) => $query->searchAthlete($filters['search']))
            ->when($filters['sport_id'] ?? null, fn ($query, $sportId) => $query->where(function ($nested) use ($sportId) {
                $nested->whereHas('athlete', fn ($athleteQuery) => $athleteQuery->where('sport_id', $sportId))
                    ->orWhereHas('session', fn ($sessionQuery) => $sessionQuery->forSport($sportId));
            }))
            ->when($filters['record_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('attended_on', $date))
            ->orderByDesc('attended_on')
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'history_page');
    }

    private function selectedSession(Request $request): ?AttendanceSession
    {
        if (! $request->filled('session_id')) {
            return null;
        }

        return AttendanceSession::query()
            ->with(['sport', 'event.sport'])
            ->find($request->integer('session_id'));
    }

    private function sessionRecords(Request $request): Collection
    {
        if (! $request->filled('session_id')) {
            return collect();
        }

        return Attendance::query()
            ->with('athlete.sport')
            ->forSession($request->integer('session_id'))
            ->get()
            ->sortBy(fn (Attendance $record) => $record->athlete?->name)
            ->values();
    }

    private function summary(string $date): array
    {
        $todayRecords = Attendance::query()
            ->whereDate('attended_on', $date)
            ->whereNotNull('attendance_session_id');

        $countedToday = (clone $todayRecords)->whereIn('status', ['Present', 'Late', 'Absent', 'Excused'])->get();

        return [
            'date' => $date,
            'todaySessions' => AttendanceSession::query()->whereDate('session_date', $date)->count(),
            'openSessions' => AttendanceSession::query()->whereDate('session_date', $date)->where('status', 'Open')->count(),
            'presentToday' => (clone $todayRecords)->where('status', 'Present')->count(),
            'lateToday' => (clone $todayRecords)->where('status', 'Late')->count(),
            'pendingToday' => (clone $todayRecords)->where('status', 'Pending')->count(),
            'absentToday' => (clone $todayRecords)->where('status', 'Absent')->count(),
            'totalAthletes' => User::query()->where('role', 'Student')->count(),
            'attendanceRate' => $countedToday->count() > 0
                ? round($countedToday->whereIn('status', ['Present', 'Late'])->count() / $countedToday->count() * 100, 1)
                : 0,
        ];
    }

    private function portalData(): array
    {
        return [
            'title' => 'Attendance Management',
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
                ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'admin.achievements'],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'route' => 'reports.index'],
            ],
            'active' => 'attendance',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => 'Administrator',
        ];
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }
}
