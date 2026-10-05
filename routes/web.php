<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MedicalController;
use App\Http\Controllers\SportController;
use App\Models\Application;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\AttendanceSessionService;
use App\Services\StudentUpdateNotifier;

Route::get('/', function () {
    return view('landing', [
        'sports' => Sport::orderBy('name')->get(),
        'announcements' => Announcement::with('sport')->where('status', 'Published')->where(fn ($query) => $query->whereNull('published_at')->orWhereDate('published_at', '<=', today()))->latest('published_at')->get(),
    ]);
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', function () {
    $credentials = request()->validate([
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
        'role' => ['required', 'in:Administrator,Student'],
    ]);

    $loginIdentifier = trim($credentials['username']);

    $user = User::where('role', $credentials['role'])
        ->where(function ($query) use ($loginIdentifier) {
            $query->where('email', $loginIdentifier)
                ->orWhere('username', $loginIdentifier);
        })
        ->first();

    if ($user && Hash::check($credentials['password'], $user->password)) {
        auth()->login($user, request()->boolean('remember'));
        request()->session()->regenerate();
        return redirect()->intended($credentials['role'] === 'Student' ? route('student.dashboard') : route('dashboard'));
    }

    return redirect()->route('login')->withErrors(['username' => 'Invalid email or password.'])->withInput(request()->only('username', 'role'));
})->name('login.submit');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

Route::post('/forgot-password', function () {
    $validated = request()->validate([
        'email' => ['required', 'email'],
    ]);

    Password::sendResetLink(['email' => $validated['email']]);

    return back()->with('success', 'If an account exists with that email, a password reset link has been sent.')->withInput(request()->only('email'));
})->name('password.email');

Route::get('/reset-password/{token}', function (string $token) {
    return view('auth.reset-password', ['token' => $token, 'email' => request('email')]);
})->name('password.reset');

Route::post('/reset-password', function () {
    $validated = request()->validate([
        'token' => ['required', 'string'],
        'email' => ['required', 'email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $status = Password::reset($validated, function (User $user, string $password) {
        $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
        $user->save();
    });

    if ($status === Password::PASSWORD_RESET) {
        return redirect()->route('login')->with('success', 'Your password has been reset successfully. You can now log in with your new password.');
    }

    return back()->withInput(request()->only('email'))->withErrors(['email' => match ($status) {
        Password::INVALID_TOKEN => 'This password reset link is invalid or has expired. Please request a new one.',
        Password::RESET_THROTTLED => 'Please wait a minute before requesting another reset link.',
        default => 'We could not reset your password. Please request a new reset link.',
    }]);
})->name('password.update');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::get('/apply', function () {
    return view('application.create', ['sports' => Sport::orderBy('name')->get()]);
})->name('application.create');

Route::post('/apply', [ApplicationController::class, 'store'])->name('application.store');

$announcements = [
    ['Basketball Tryouts This Friday', 'May 5, 2026', 'Basketball team tryouts will be held at the main court this Friday at 3:00 PM. All interested students are welcome to participate.'],
    ['Regional Sports Meet - June 2026', 'May 1, 2026', 'SNNHS will host the Regional Sports Meet in June. Athletes are encouraged to intensify their training sessions.'],
    ['New Sports Equipment Available', 'April 28, 2026', 'The school has acquired new training equipment for volleyball and track and field.'],
];

$studentNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'student.dashboard'],
    ['key' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'route' => 'student.sports'],
    ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'student.calendar'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'icon' => 'calendar', 'route' => 'student.schedule'],
    ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'student.announcements'],
    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'student.attendance'],
    ['key' => 'application', 'label' => 'My Application', 'icon' => 'clipboard', 'route' => 'student.application'],
    ['key' => 'coach', 'label' => 'Coach Info', 'icon' => 'users', 'route' => 'student.coach'],
    ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'user', 'route' => 'student.profile'],
];

$calendarData = function (?string $month = null): array {
    $monthValue = $month ?: request('month', now()->format('Y-m'));
    $calendarMonth = Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $calendarMonth->copy()->endOfMonth();

    $eventsQuery = Event::with(['sport', 'coach'])
        ->when(auth()->user()?->role === 'Student', fn ($query) => $query->where('status', 'Scheduled'))
        ->when(request('sport_id'), fn ($query, $sportId) => $query->where('sport_id', $sportId))
        ->when(request('venue'), fn ($query, $venue) => $query->where('venue', $venue))
        ->when(request('event_type'), fn ($query, $eventType) => $query->where('event_type', $eventType))
        ->where(function ($query) use ($monthStart, $monthEnd) {
            $query->whereBetween('starts_at', [$monthStart, $monthEnd])
                ->orWhereBetween('ends_at', [$monthStart, $monthEnd]);
        })
        ->when(auth()->user()?->role === 'Student', fn ($query) => $query->where(fn ($sportQuery) => $sportQuery->whereNull('sport_id')->orWhere('sport_id', auth()->user()->sport_id)));

    $events = $eventsQuery->orderBy('starts_at')->get();
    $calendarEvents = $events->groupBy(fn ($event) => $event->starts_at->toDateString());

    $days = [];
    $dayCursor = $calendarMonth->copy()->startOfWeek(Carbon::SUNDAY);
    $lastDay = $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
    while ($dayCursor->lte($lastDay)) {
        $days[] = $dayCursor->copy();
        $dayCursor->addDay();
    }

    $studentEvents = Event::query()
        ->when(auth()->user()?->role === 'Student', fn ($query) => $query->where('status', 'Scheduled')->where(fn ($sportQuery) => $sportQuery->whereNull('sport_id')->orWhere('sport_id', auth()->user()->sport_id)));

    return [
        'calendarMonth' => $calendarMonth,
        'calendarDays' => $days,
        'calendarEvents' => $calendarEvents,
        'summary' => [
            'month' => $events->count(),
            'week' => (clone $studentEvents)->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'today' => (clone $studentEvents)->where(fn ($query) => $query->whereDate('starts_at', today())->orWhereDate('ends_at', today()))->count(),
        ],
        'sports' => Sport::orderBy('name')->get(),
        'venues' => Event::query()->whereNotNull('venue')->distinct()->orderBy('venue')->pluck('venue'),
        'eventTypes' => EventController::eventTypes(),
        'upcomingEvents' => Event::with('sport')->where('status', 'Scheduled')->where('starts_at', '>=', now())->when(auth()->user()?->role === 'Student', fn ($query) => $query->where(fn ($sportQuery) => $sportQuery->whereNull('sport_id')->orWhere('sport_id', auth()->user()->sport_id)))->orderBy('starts_at')->limit(5)->get(),
    ];
};

$studentPage = function (string $page, array $data = []) use ($studentNav, $announcements) {
    abort_unless(auth()->user()?->role === 'Student', 403);
    $athlete = auth()->user();
    $publishedAnnouncements = Announcement::with('sport')->where('status', 'Published')->where(fn ($query) => $query->whereNull('sport_id')->orWhere('sport_id', $athlete->sport_id))->where(fn ($query) => $query->whereNull('published_at')->orWhereDate('published_at', '<=', today()))->latest('published_at')->get();
    $studentApplication = $athlete->applications()->with('sportCategory')->latest()->first();
    $upcomingStudentEventsQuery = Event::with(['sport', 'coach'])
        ->where('status', 'Scheduled')
        ->where('starts_at', '>=', now())
        ->where(fn ($query) => $query->whereNull('sport_id')->orWhere('sport_id', $athlete->sport_id))
        ->orderBy('starts_at');
    $upcomingStudentEvents = (clone $upcomingStudentEventsQuery)->limit(5)->get();
    $dashboardData = [];

    if ($page === 'dashboard') {
        $attendanceService = app(AttendanceSessionService::class);
        $attendanceCounts = $athlete->attendanceRecords()
            ->counted()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $attendanceTotal = (int) $attendanceCounts->sum();
        $eligibleSessions = $athlete->sport_id
            ? AttendanceSession::query()
                ->with(['event.sport', 'event.coach', 'sport', 'attendanceRecords' => fn ($query) => $query->where('user_id', $athlete->id)])
                ->visibleToAthlete($athlete)
                ->whereIn('status', ['Open', 'Closed'])
                ->whereDate('session_date', '>=', today()->toDateString())
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get()
            : collect();
        $eligibleOpenSessions = $eligibleSessions->filter(fn ($session) => $session->isOpen() && $session->session_date?->isToday())->values();
        $nextEvent = (clone $upcomingStudentEventsQuery)->first();
        $nextTraining = $athlete->sport_id
            ? $eligibleSessions->first(fn ($session) => ! $session->session_date?->isToday() || ! $session->start_time || Carbon::parse($session->session_date->toDateString().' '.$session->start_time)->gte(now()))
            : null;
        $activityOptions = collect();

        if ($nextEvent) {
            $activityOptions->push([
                'title' => $nextEvent->title,
                'sport' => $nextEvent->sport?->name ?? 'All sports',
                'startsAt' => $nextEvent->starts_at,
                'endsAt' => $nextEvent->ends_at,
                'venue' => $nextEvent->venue,
                'coach' => $nextEvent->coach?->name,
            ]);
        }

        if ($nextTraining) {
            $activityOptions->push([
                'title' => $nextTraining->title,
                'sport' => $nextTraining->sport?->name ?? 'Unassigned sport',
                'startsAt' => Carbon::parse($nextTraining->session_date->toDateString().' '.($nextTraining->start_time ?: '00:00')),
                'endsAt' => $nextTraining->end_time ? Carbon::parse($nextTraining->session_date->toDateString().' '.$nextTraining->end_time) : null,
                'venue' => $nextTraining->venue,
                'coach' => null,
            ]);
        }

        $dashboardData = [
            'dashboardUpcomingCount' => (clone $upcomingStudentEventsQuery)->count(),
            'dashboardAttendanceCounts' => [
                'present' => (int) $attendanceCounts->get('Present', 0),
                'late' => (int) $attendanceCounts->get('Late', 0),
                'absent' => (int) $attendanceCounts->get('Absent', 0),
                'excused' => (int) $attendanceCounts->get('Excused', 0),
                'percentage' => $attendanceService->rateFor($athlete),
            ],
            'dashboardOpenSessions' => $eligibleOpenSessions,
            'dashboardUpcomingAttendance' => $eligibleSessions->filter(fn ($session) => ! $session->session_date?->isToday())->values(),
            'dashboardNextActivity' => $activityOptions->sortBy('startsAt')->first(),
            'dashboardUnreadCount' => $athlete->unreadNotifications()->count(),
            'dashboardNotifications' => $athlete->notifications()->latest()->get(),
        ];
    }
    $content = [
        'dashboard' => ['Dashboard', "Here's what's happening with your sports activities", null],
        'sports' => ['Sports Programs', 'View sports programs managed by SNNHS', null],
        'calendar' => ['Calendar', 'View your sports activities and important dates', null],
        'schedule' => ['My Schedule', 'Your upcoming training sessions and events', null],
        'announcements' => ['Announcements', 'Stay updated with the latest news and updates', null],
        'attendance' => ['Attendance Record', 'Track your attendance for training sessions and events', null],
        'application' => ['My Application', 'View your sports application status and feedback', null],
        'coach' => ['Coach Information', 'Learn more about your coach', null],
        'profile' => ['My Profile', 'Manage your personal information', ['label' => 'Edit Profile', 'url' => '#']],
    ][$page];
    return view('student.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $studentNav, 'announcements' => $publishedAnnouncements, 'studentApplication' => $studentApplication, 'upcomingStudentEvents' => $upcomingStudentEvents, 'athlete' => $athlete, 'roleLabel' => 'Athlete Portal', 'userName' => $athlete->name, 'userRole' => $athlete->sport?->name ?? 'Unassigned sport'], $dashboardData, $data));
};

$announcementIsVisible = fn (Announcement $announcement): bool => $announcement->status === 'Published'
    && (! $announcement->published_at || $announcement->published_at->lessThanOrEqualTo(today()));
$notifyStudentsAboutAnnouncement = function (Announcement $announcement, string $title, string $message, ?array $sportIds = null) {
    $sportIds ??= $announcement->sport_id ? [$announcement->sport_id] : null;

    app(StudentUpdateNotifier::class)->notifyStudents(
        $sportIds,
        $title,
        $message,
        route('student.announcements'),
    );
};

Route::get('/student', fn () => redirect()->route('student.dashboard'));
Route::get('/student/dashboard', fn () => $studentPage('dashboard'))->middleware('auth')->name('student.dashboard');
Route::get('/student/sports', fn () => $studentPage('sports', ['studentSports' => Sport::where('status', 'Active')->with('coaches')->orderBy('name')->get()]))->middleware('auth')->name('student.sports');
Route::get('/student/calendar', fn () => $studentPage('calendar', $calendarData(request('month'))))->middleware('auth')->name('student.calendar');
Route::get('/student/schedule', fn () => $studentPage('schedule', [
    'events' => Event::where('status', 'Scheduled')
        ->where('starts_at', '>=', now())
        ->where(function ($query) {
            $query->whereNull('sport_id')->orWhere('sport_id', auth()->user()->sport_id);
        })
        ->orderBy('starts_at')
        ->get(),
]))->middleware('auth')->name('student.schedule');
Route::get('/student/announcements', fn () => $studentPage('announcements'))->middleware('auth')->name('student.announcements');
Route::get('/student/attendance', function () use ($studentPage) {
    $athlete = auth()->user();
    abort_unless($athlete?->role === 'Student', 403);

    $attendanceService = app(AttendanceSessionService::class);

    $sessions = $athlete->sport_id
        ? AttendanceSession::query()
            ->with(['sport', 'event.sport', 'attendanceRecords' => fn ($query) => $query->where('user_id', $athlete->id)])
            ->visibleToAthlete($athlete)
            ->whereIn('status', ['Open', 'Closed'])
            ->whereDate('session_date', '>=', today()->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get()
        : collect();

    $records = Attendance::query()
        ->with(['session.sport', 'session.event.sport', 'event.sport'])
        ->where('user_id', $athlete->id)
        ->orderByDesc('attended_on')
        ->orderByDesc('id')
        ->get();

    $counted = $records->filter(fn ($record) => in_array($record->status, config('attendance.counted_statuses'), true)
        && (! $record->session || $record->session->status !== 'Cancelled'));

    $presentCount = $counted->whereIn('status', ['Present', 'Late'])->count();

    return $studentPage('attendance', [
        'attendanceRecords' => $records,
        'openAttendanceSessions' => $sessions->filter(fn ($session) => $session->isOpen() && $session->session_date?->isToday()),
        'upcomingAttendanceSessions' => $sessions->filter(fn ($session) => ! $session->session_date?->isToday()),
        'attendanceStats' => [
            'total' => $counted->count(),
            'present' => $records->where('status', 'Present')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
            'pending' => $records->where('status', 'Pending')->count(),
            'rate' => $attendanceService->rateFor($athlete),
        ],
    ]);
})->middleware('auth')->name('student.attendance');
Route::post('/student/attendance/sessions/{session}/check-in', function (AttendanceSession $session) {
    $student = auth()->user();

    $result = app(AttendanceSessionService::class)->checkIn($session, $student);

    if (! $result['checkedIn']) {
        return back()->withErrors(['attendance' => 'You have already checked in for this session.']);
    }

    app(AttendanceSessionService::class)->notifyAdministrators(
        $student->name.' checked in for '.$result['label'].'.',
        route('admin.attendance', ['session_id' => $session->id])
    );

    return back()->with('success', 'You are checked in for '.$result['label'].' at '.($result['time'] ?? now())->format('g:i A').'.');
})->middleware('auth')->name('student.attendance.check-in');
Route::get('/student/application', fn () => $studentPage('application'))->middleware('auth')->name('student.application');
Route::get('/student/coach', fn () => $studentPage('coach', ['coachSports' => auth()->user()->sportsWithCoaches()]))->middleware('auth')->name('student.coach');
Route::get('/student/profile', fn () => $studentPage('profile', ['athlete' => auth()->user()]))->middleware('auth')->name('student.profile');
Route::patch('/student/profile', function () {
    abort_unless(auth()->user()?->role === 'Student', 403);
    $validated = request()->validate(['phone' => ['nullable', 'string', 'max:50'], 'profile_photo' => ['nullable', 'image', 'max:2048']]);
    if (request()->hasFile('profile_photo')) { $validated['profile_photo_path'] = request()->file('profile_photo')->store('profile-photos', 'public'); }
    auth()->user()->update($validated);
    return back()->with('success', 'Profile updated.');
})->middleware('auth')->name('student.profile.update');

Route::post('/notifications/{notification}/read', function (string $notification) {
    abort_unless(auth()->check(), 403);
    $record = auth()->user()->notifications()->whereKey($notification)->firstOrFail();
    $record->markAsRead();

    return back();
})->middleware('auth')->name('notifications.read');

Route::post('/notifications/read-all', function () {
    abort_unless(auth()->check(), 403);
    auth()->user()->unreadNotifications->each->markAsRead();

    return back();
})->middleware('auth')->name('notifications.read-all');

$adminNav = [
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
];

Route::get('/notifications', function () use ($studentNav, $adminNav) {
    $user = auth()->user();
    $isStudent = $user->role === 'Student';

    return view('notifications.index', [
        'notifications' => $user->notifications()->latest()->simplePaginate(20),
        'title' => 'Notifications',
        'navItems' => $isStudent ? $studentNav : $adminNav,
        'active' => 'notifications',
        'roleLabel' => $isStudent ? 'Athlete Portal' : 'Admin Portal',
        'userName' => $user->name,
        'userRole' => $user->sport?->name ?? $user->role,
        'homeUrl' => $isStudent ? route('student.dashboard') : route('dashboard'),
    ]);
})->middleware('auth')->name('notifications.index');

$adminPage = function (string $page, array $data = []) use ($adminNav, $announcements) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $content = [
        'dashboard' => ['Admin Dashboard', 'Overview of SNNHS Sports Activity Hub', null],
        'calendar' => ['Calendar', 'View scheduled sports activities and events', null],
        'applications' => ['Applications Management', 'Review and process athlete applications', null],
        'sports' => ['Sports Management', 'Manage sports programs and categories', 'Add New Sport'],
        'athletes' => ['Athlete Management', 'Manage registered athlete accounts', null],
        'medical' => ['Medical Records', 'Manage athlete health information and clearances', 'Add Medical Record'],
        'coaches' => ['Coach Management', 'Manage coach records and assignments', 'Add New Coach'],
        'events' => ['Event Scheduling', 'Manage training sessions and competitions', 'Create Event'],
        'attendance' => ['Attendance Management', 'Track and record athlete attendance', null],
        'announcements' => ['Announcement Management', 'Create and manage public announcements', 'Create Announcement'],
        'reports' => ['Reports', 'Generate and download reports', null],
    ][$page];
    return view('admin.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $adminNav, 'announcements' => $announcements, 'roleLabel' => 'Admin Portal', 'userName' => 'Admin User', 'userRole' => 'Administrator'], $data));
};

Route::get('/dashboard', function () use ($adminPage, $calendarData) {
    $countedToday = Attendance::whereDate('attended_on', today())
        ->whereNotNull('attendance_session_id')
        ->whereIn('status', config('attendance.counted_statuses'))
        ->get();
    $attendanceRate = $countedToday->count() > 0
        ? round($countedToday->whereIn('status', ['Present', 'Late'])->count() / $countedToday->count() * 100, 1)
        : 0;

    return $adminPage('dashboard', array_merge($calendarData(), [
    'stats' => [
        ['value' => Sport::count(), 'label' => 'Total Sports', 'action' => 'View details', 'route' => 'sports.index'],
        ['value' => User::where('role', 'Student')->count(), 'label' => 'Active Athletes', 'action' => 'View athletes', 'route' => 'athletes.index'],
        ['value' => Sport::whereHas('athletes', fn ($query) => $query->where('role', 'Student'))->count(), 'label' => 'Active Teams', 'action' => 'View sports', 'route' => 'sports.index'],
        ['value' => Event::where('status', 'Scheduled')->where('starts_at', '>=', now())->count(), 'label' => 'Upcoming Events', 'action' => 'View events', 'route' => 'events.index'],
        ['value' => AttendanceSession::whereDate('session_date', today())->count(), 'label' => "Today's Sessions", 'action' => 'View attendance', 'route' => 'admin.attendance'],
        ['value' => Attendance::whereDate('attended_on', today())->whereIn('status', ['Present', 'Late'])->whereNotNull('attendance_session_id')->count(), 'label' => 'Present Today', 'action' => 'View attendance', 'route' => 'admin.attendance'],
        ['value' => Attendance::whereDate('attended_on', today())->where('status', 'Pending')->whereNotNull('attendance_session_id')->count(), 'label' => 'Pending Check-ins', 'action' => 'View attendance', 'route' => 'admin.attendance'],
        ['value' => $attendanceRate.'%', 'label' => 'Attendance Rate', 'action' => 'Full report', 'route' => 'reports.index'],
        ['value' => Application::where('status', 'Pending')->count(), 'label' => 'Pending Applications', 'action' => 'Review applications', 'route' => 'admin.applications'],
        ['value' => MedicalRecord::where('medical_status', 'Cleared')->count().' / '.User::where('role', 'Student')->count(), 'label' => 'Medical Clearance', 'action' => 'View records', 'route' => 'admin.medical'],
    ],
    'upcomingEvents' => Event::with('sport')->where('status', 'Scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
    'athletesBySport' => Sport::withCount(['athletes' => fn ($query) => $query->where('role', 'Student')])->orderBy('name')->get(),
    'medicalStatusCounts' => [
        'Cleared' => MedicalRecord::where('medical_status', 'Cleared')->count(),
        'Pending' => MedicalRecord::where('medical_status', 'Pending')->count(),
        'Not Cleared' => MedicalRecord::where('medical_status', 'Not Cleared')->count(),
        'Restricted' => MedicalRecord::where('medical_status', 'Restricted')->count(),
    ],
    'pendingApplications' => Application::with('sportCategory')->where('status', 'Pending')->latest()->limit(5)->get(),
    'attentionItems' => collect([
        Application::where('status', 'Pending')->count() ? ['tone' => 'warning', 'message' => Application::where('status', 'Pending')->count().' application(s) waiting for review', 'route' => 'admin.applications', 'action' => 'Review'] : null,
        User::where('role', 'Student')->whereDoesntHave('medicalRecords')->count() ? ['tone' => 'danger', 'message' => User::where('role', 'Student')->whereDoesntHave('medicalRecords')->count().' athlete(s) need medical clearance', 'route' => 'admin.medical', 'action' => 'View'] : null,
        MedicalRecord::whereNotNull('next_checkup_date')->whereBetween('next_checkup_date', [today(), today()->addDays(30)])->count() ? ['tone' => 'warning', 'message' => MedicalRecord::whereNotNull('next_checkup_date')->whereBetween('next_checkup_date', [today(), today()->addDays(30)])->count().' medical checkup(s) due within 30 days', 'route' => 'admin.medical', 'action' => 'View'] : null,
    ])->filter()->values(),
    'recentActivity' => collect([
        ...User::where('role', 'Student')->latest('created_at')->limit(2)->get()->map(fn ($athlete) => ['label' => $athlete->name.' was added as an athlete', 'date' => $athlete->created_at])->all(),
        ...Event::with('sport')->latest('created_at')->limit(2)->get()->map(fn ($event) => ['label' => $event->title.' was created', 'date' => $event->created_at])->all(),
        ...Application::latest('created_at')->limit(2)->get()->map(fn ($application) => ['label' => $application->name.' submitted a sports application', 'date' => $application->created_at])->all(),
        ...MedicalRecord::with('athlete')->latest('updated_at')->limit(2)->get()->map(fn ($record) => ['label' => 'Medical record updated'.($record->athlete?->name ? ' for '.$record->athlete->name : ''), 'date' => $record->updated_at])->all(),
        ...AttendanceSession::latest('created_at')->limit(2)->get()->map(fn ($session) => ['label' => 'Attendance session created for '.$session->effectiveSportName(), 'date' => $session->created_at])->all(),
    ])->sortByDesc('date')->take(5)->values(),
    ]));
})->middleware('auth')->name('dashboard');
Route::get('/admin/calendar', fn () => $adminPage('calendar', $calendarData(request('month'))))->middleware('auth')->name('admin.calendar');
Route::get('/applications', [ApplicationController::class, 'index'])->middleware('auth')->name('admin.applications');
Route::get('/applications/{application}', [ApplicationController::class, 'show'])->middleware('auth')->name('applications.show');
Route::patch('/applications/{application}', [ApplicationController::class, 'update'])->middleware('auth')->name('applications.update');
Route::post('/applications/{application}/approve', [ApplicationController::class, 'approve'])->middleware('auth')->name('applications.approve');
Route::post('/applications/{application}/reject', [ApplicationController::class, 'reject'])->middleware('auth')->name('applications.reject');
Route::post('/applications/{application}/request-documents', [ApplicationController::class, 'requestDocuments'])->middleware('auth')->name('applications.request-documents');
Route::post('/applications/{application}/documents/{document}/verify', [ApplicationController::class, 'verifyDocument'])->middleware('auth')->name('applications.documents.verify');
Route::post('/applications/{application}/documents/{document}/reject', [ApplicationController::class, 'rejectDocument'])->middleware('auth')->name('applications.documents.reject');
Route::get('/applications/{application}/documents/{document}', function (Application $application, string $document) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $columns = ['medical' => 'medical_certificate_path', 'birth' => 'birth_certificate_path', 'consent' => 'parent_consent_path'];
    abort_unless(isset($columns[$document]) && $application->{$columns[$document]}, 404);
    $path = $application->{$columns[$document]};
    abort_unless(Storage::disk('private')->exists($path), 404, 'The requested document is no longer available.');
    return Storage::disk('private')->download($path);
})->middleware('auth')->name('applications.documents.download');
Route::resource('sports', SportController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('auth');
Route::get('/sports/{sport}', [SportController::class, 'show'])->middleware('auth')->name('sports.show');
Route::get('/athletes', [AthleteController::class, 'index'])->middleware('auth')->name('athletes.index');
Route::get('/athletes/create/{application}', [AthleteController::class, 'create'])->middleware('auth')->name('athletes.create');
Route::post('/athletes', [AthleteController::class, 'store'])->middleware('auth')->name('athletes.store');
Route::get('/athletes/{athlete}', [\App\Http\Controllers\AthleteController::class, 'show'])->middleware('auth')->name('athletes.show');
Route::get('/athletes/{athlete}/edit', [\App\Http\Controllers\AthleteController::class, 'edit'])->middleware('auth')->name('athletes.edit');
Route::put('/athletes/{athlete}', [\App\Http\Controllers\AthleteController::class, 'update'])->middleware('auth')->name('athletes.update');
Route::delete('/athletes/{athlete}', [\App\Http\Controllers\AthleteController::class, 'destroy'])->middleware('auth')->name('athletes.destroy');
Route::get('/admin/medical', [MedicalController::class, 'index'])->middleware('auth')->name('admin.medical');
Route::get('/admin/medical/create', [MedicalController::class, 'create'])->middleware('auth')->name('admin.medical.create');
Route::post('/admin/medical', [MedicalController::class, 'store'])->middleware('auth')->name('admin.medical.store');
Route::get('/admin/medical/{medical}', [MedicalController::class, 'show'])->middleware('auth')->name('admin.medical.show');
Route::get('/admin/medical/{medical}/edit', [MedicalController::class, 'edit'])->middleware('auth')->name('admin.medical.edit');
Route::put('/admin/medical/{medical}', [MedicalController::class, 'update'])->middleware('auth')->name('admin.medical.update');
Route::delete('/admin/medical/{medical}', [MedicalController::class, 'destroy'])->middleware('auth')->name('admin.medical.destroy');
Route::get('/admin/medical/{medical}/certificate', [MedicalController::class, 'downloadCertificate'])->middleware('auth')->name('admin.medical.certificate');
Route::resource('coaches', \App\Http\Controllers\CoachController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])->middleware('auth');
Route::resource('events', EventController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('auth');
Route::get('/events/{event}', [EventController::class, 'show'])->middleware('auth')->name('events.show');
Route::get('/admin/events', fn () => redirect()->route('events.index'))->middleware('auth')->name('admin.events.index');
Route::get('/admin/attendance', [AttendanceController::class, 'index'])->middleware('auth')->name('admin.attendance');
Route::post('/admin/attendance/sessions', [AttendanceController::class, 'store'])->middleware('auth')->name('admin.attendance.sessions.store');
Route::patch('/admin/attendance/sessions/{session}/open', [AttendanceController::class, 'open'])->middleware('auth')->name('admin.attendance.sessions.open');
Route::patch('/admin/attendance/sessions/{session}/close', [AttendanceController::class, 'close'])->middleware('auth')->name('admin.attendance.sessions.close');
Route::patch('/admin/attendance/sessions/{session}/cancel', [AttendanceController::class, 'cancel'])->middleware('auth')->name('admin.attendance.sessions.cancel');
Route::patch('/admin/attendance/sessions/{session}/sync', [AttendanceController::class, 'sync'])->middleware('auth')->name('admin.attendance.sessions.sync');
Route::delete('/admin/attendance/sessions/{session}', [AttendanceController::class, 'destroy'])->middleware('auth')->name('admin.attendance.sessions.destroy');
Route::get('/admin/attendance/sessions/{session}/export', [AttendanceController::class, 'export'])->middleware('auth')->name('admin.attendance.sessions.export');
Route::put('/admin/attendance/records/{attendance}', [AttendanceController::class, 'updateRecord'])->middleware('auth')->name('admin.attendance.records.update');

Route::get('/admin/announcements', function () use ($adminPage) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);

    $announcements = Announcement::with('sport')
        ->when(request('search'), fn ($query, $search) => $query->where(function ($announcementQuery) use ($search) {
            $announcementQuery->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%");
        }))
        ->when(request('sport_id'), fn ($query, $sportId) => $query->where('sport_id', $sportId))
        ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
        ->when(request('date'), fn ($query, $date) => $query->whereDate('published_at', $date))
        ->orderByRaw("CASE WHEN status = 'Published' THEN 0 WHEN status = 'Scheduled' THEN 1 WHEN status = 'Draft' THEN 2 WHEN status = 'Archived' THEN 3 ELSE 4 END")
        ->latest('published_at')
        ->get();

    return $adminPage('announcements', [
        'announcements' => $announcements,
        'announcement' => request('announcement_id') ? Announcement::find(request('announcement_id')) : null,
        'summary' => [
            'total' => Announcement::count(),
            'published' => Announcement::where('status', 'Published')->count(),
            'scheduled' => Announcement::where('status', 'Scheduled')->count(),
            'drafts' => Announcement::where('status', 'Draft')->count(),
        ],
        'sports' => Sport::orderBy('name')->get(),
        'statuses' => ['Draft', 'Published', 'Scheduled', 'Archived'],
    ]);
})->middleware('auth')->name('admin.announcements');

Route::post('/admin/announcements', function () use ($announcementIsVisible, $notifyStudentsAboutAnnouncement) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $validated = request()->validate([
        'title' => ['required', 'string', 'max:255'],
        'body' => ['required', 'string'],
        'sport_id' => ['nullable', 'exists:sports,id'],
        'published_at' => ['nullable', 'date'],
        'status' => ['required', 'in:Draft,Published,Scheduled,Archived'],
    ]);

    $announcement = Announcement::create($validated);
    if ($announcementIsVisible($announcement)) {
        $notifyStudentsAboutAnnouncement($announcement, 'New announcement', $announcement->title.' was published.');
    }

    return redirect()->route('admin.announcements')->with('success', 'Announcement created successfully.');
})->middleware('auth')->name('admin.announcements.store');

Route::put('/admin/announcements/{announcement}', function (Announcement $announcement) use ($announcementIsVisible, $notifyStudentsAboutAnnouncement) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $validated = request()->validate([
        'title' => ['required', 'string', 'max:255'],
        'body' => ['required', 'string'],
        'sport_id' => ['nullable', 'exists:sports,id'],
        'published_at' => ['nullable', 'date'],
        'status' => ['required', 'in:Draft,Published,Scheduled,Archived'],
    ]);

    $wasVisible = $announcementIsVisible($announcement);
    $previousSportId = $announcement->sport_id;
    $announcement->update($validated);
    $isVisible = $announcementIsVisible($announcement);

    if ($isVisible && ($announcement->wasChanged(['title', 'body', 'sport_id', 'published_at', 'status']))) {
        $sportIds = $wasVisible && ($previousSportId === null || $announcement->sport_id === null)
            ? null
            : ($wasVisible ? [$previousSportId, $announcement->sport_id] : null);
        $notifyStudentsAboutAnnouncement(
            $announcement,
            $wasVisible ? 'Announcement updated' : 'New announcement',
            $announcement->title.($wasVisible ? ' was updated.' : ' was published.'),
            $sportIds,
        );
    } elseif ($wasVisible && ! $isVisible) {
        $notifyStudentsAboutAnnouncement(
            $announcement,
            'Announcement update',
            $announcement->title.' is no longer available.',
            $previousSportId ? [$previousSportId] : null,
        );
    }

    return redirect()->route('admin.announcements', ['announcement_id' => $announcement->id])->with('success', 'Announcement updated successfully.');
})->middleware('auth')->name('admin.announcements.update');

Route::delete('/admin/announcements/{announcement}', function (Announcement $announcement) use ($announcementIsVisible, $notifyStudentsAboutAnnouncement) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);

    if ($announcementIsVisible($announcement)) {
        $notifyStudentsAboutAnnouncement($announcement, 'Announcement update', $announcement->title.' is no longer available.');
    }

    $announcement->delete();

    return redirect()->route('admin.announcements')->with('success', 'Announcement deleted successfully.');
})->middleware('auth')->name('admin.announcements.destroy');

Route::post('/admin/announcements/{announcement}/publish', function (Announcement $announcement) use ($announcementIsVisible, $notifyStudentsAboutAnnouncement) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $wasVisible = $announcementIsVisible($announcement);
    $announcement->update(['status' => 'Published']);

    if (! $wasVisible && $announcementIsVisible($announcement)) {
        $notifyStudentsAboutAnnouncement($announcement, 'New announcement', $announcement->title.' was published.');
    }

    return back()->with('success', 'Announcement published successfully.');
})->middleware('auth')->name('admin.announcements.publish');

Route::post('/admin/announcements/{announcement}/archive', function (Announcement $announcement) use ($announcementIsVisible, $notifyStudentsAboutAnnouncement) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $wasVisible = $announcementIsVisible($announcement);
    $announcement->update(['status' => 'Archived']);

    if ($wasVisible) {
        $notifyStudentsAboutAnnouncement($announcement, 'Announcement update', $announcement->title.' has been archived.');
    }

    return back()->with('success', 'Announcement archived successfully.');
})->middleware('auth')->name('admin.announcements.archive');
Route::get('/reports', function () use ($adminPage) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);

    $validated = request()->validate([
        'report_type' => ['nullable', 'in:attendance,athletes,sports,events,applications'],
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date', 'after_or_equal:from'],
        'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
        'event_id' => ['nullable', 'integer', 'exists:events,id'],
        'athlete_id' => ['nullable', 'integer', 'exists:users,id'],
        'status' => ['nullable', 'in:Present,Late,Absent,Excused'],
        'search' => ['nullable', 'string', 'max:100'],
        'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    $reportType = $validated['report_type'] ?? 'attendance';
    $perPage = (int) ($validated['per_page'] ?? 25);
    $search = trim($validated['search'] ?? '');

    $attendanceQuery = Attendance::with(['athlete.sport', 'session.sport', 'event.sport'])
        ->when($validated['from'] ?? null, fn ($query, $value) => $query->whereDate('attended_on', '>=', $value))
        ->when($validated['to'] ?? null, fn ($query, $value) => $query->whereDate('attended_on', '<=', $value))
        ->when($validated['sport_id'] ?? null, fn ($query, $value) => $query->where(function ($nested) use ($value) {
            $nested->whereHas('athlete', fn ($athletes) => $athletes->where('sport_id', $value))
                ->orWhereHas('session', fn ($sessionQuery) => $sessionQuery->forSport($value));
        }))
        ->when($validated['event_id'] ?? null, fn ($query, $value) => $query->where('event_id', $value))
        ->when($validated['athlete_id'] ?? null, fn ($query, $value) => $query->where('user_id', $value))
        ->when($validated['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
        ->when($search !== '', function ($query) use ($search) {
            $query->where(function ($nested) use ($search) {
                $nested->whereHas('athlete', fn ($athlete) => $athlete->where('name', 'like', "%{$search}%")->orWhere('student_id', 'like', "%{$search}%"))
                    ->orWhereHas('session', fn ($session) => $session->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('event', fn ($event) => $event->where('title', 'like', "%{$search}%"));
            });
        })
        ->latest('attended_on')->latest('id');

    $filteredAttendance = (clone $attendanceQuery)->get();
    $rateRecords = $filteredAttendance->filter(fn ($record) => in_array($record->status, config('attendance.counted_statuses'), true)
        && (! $record->session || $record->session->status !== 'Cancelled'));
    $attendanceTotals = $rateRecords->groupBy('user_id')->map(fn ($records) => [
        'total' => $records->count(),
        'present' => $records->whereIn('status', ['Present', 'Late'])->count(),
    ]);
    $filteredAttendance->each(function ($record) use ($attendanceTotals) {
        $totals = $attendanceTotals->get($record->user_id, ['total' => 0, 'present' => 0]);
        $record->report_rate = $totals['total'] > 0 ? round(($totals['present'] / $totals['total']) * 100, 1) : 0;
    });
    $paginate = function ($items) use ($perPage) {
        $items = collect($items);
        $page = LengthAwarePaginator::resolveCurrentPage();
        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    };

    $statusCounts = collect(['Present', 'Absent', 'Late', 'Excused'])->mapWithKeys(fn ($status) => [$status => $filteredAttendance->where('status', $status)->count()]);
    $attendanceTotal = $rateRecords->count();
    $attendanceRate = $attendanceTotal > 0 ? round(($rateRecords->whereIn('status', ['Present', 'Late'])->count() / $attendanceTotal) * 100, 1) : 0;
    $attendanceByMonth = $filteredAttendance->groupBy(fn ($record) => $record->attended_on->format('Y-m'))->sortKeys()->map(fn ($records) => [
        'label' => Carbon::createFromFormat('Y-m', $records->first()->attended_on->format('Y-m'))->format('M Y'),
        'Present' => $records->where('status', 'Present')->count(),
        'Absent' => $records->where('status', 'Absent')->count(),
        'Late' => $records->where('status', 'Late')->count(),
    ])->values();

    $athletes = User::where('role', 'Student')->with(['sport.coaches', 'applications', 'attendanceRecords'])
        ->when($validated['sport_id'] ?? null, fn ($query, $value) => $query->where('sport_id', $value))
        ->when($validated['athlete_id'] ?? null, fn ($query, $value) => $query->whereKey($value))
        ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('student_id', 'like', "%{$search}%")))
        ->orderBy('name')->get()->map(function ($athlete) use ($rateRecords) {
            $records = $rateRecords->where('user_id', $athlete->id);
            $total = $records->count();
            $present = $records->whereIn('status', ['Present', 'Late'])->count();
            $application = $athlete->applications->sortByDesc('created_at')->first();
            $athlete->report_total = $total;
            $athlete->report_present = $present;
            $athlete->report_absent = $records->where('status', 'Absent')->count();
            $athlete->report_rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
            $athlete->report_application = $application;
            return $athlete;
        });

    $sports = Sport::withCount(['athletes as athlete_count' => fn ($query) => $query->where('role', 'Student'), 'coaches', 'events'])
        ->when($validated['sport_id'] ?? null, fn ($query, $value) => $query->whereKey($value))
        ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
        ->orderBy('name')->get()->map(function ($sport) use ($rateRecords) {
            $records = $rateRecords->filter(fn ($record) => $record->athlete?->sport_id === $sport->id);
            $sport->report_rate = $records->count() ? round(($records->whereIn('status', ['Present', 'Late'])->count() / $records->count()) * 100, 1) : 0;
            return $sport;
        });

    $events = Event::with(['sport', 'attendanceRecords'])
        ->when($validated['sport_id'] ?? null, fn ($query, $value) => $query->where('sport_id', $value))
        ->when($validated['event_id'] ?? null, fn ($query, $value) => $query->whereKey($value))
        ->when($validated['from'] ?? null, fn ($query, $value) => $query->whereDate('starts_at', '>=', $value))
        ->when($validated['to'] ?? null, fn ($query, $value) => $query->whereDate('starts_at', '<=', $value))
        ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
        ->orderByDesc('starts_at')->get()->map(function ($event) use ($filteredAttendance) {
            $records = $filteredAttendance->where('event_id', $event->id);
            $event->report_present = $records->where('status', 'Present')->count();
            $event->report_absent = $records->where('status', 'Absent')->count();
            $event->report_late = $records->where('status', 'Late')->count();
            $event->report_athletes = $records->pluck('user_id')->unique()->count();
            $event->report_rate = $records->count() ? round(($event->report_present / $records->count()) * 100, 1) : 0;
            return $event;
        });

    $applications = Application::with('sportCategory')
        ->when($validated['sport_id'] ?? null, fn ($query, $value) => $query->where('sport_id', $value))
        ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('student_id', 'like', "%{$search}%")))
        ->when($validated['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
        ->when($validated['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
        ->latest()->get();

    $athletesPerSport = $sports->map(fn ($sport) => ['label' => $sport->name, 'value' => $sport->athlete_count]);
    $eventsPerSport = $sports->map(fn ($sport) => ['label' => $sport->name, 'value' => $sport->events_count]);
    $lowAttendance = $athletes->filter(fn ($athlete) => $athlete->report_total > 0 && $athlete->report_rate < 75)->values();
    $reportData = match ($reportType) {
        'athletes' => $paginate($athletes),
        'sports' => $paginate($sports),
        'events' => $paginate($events),
        'applications' => $paginate($applications),
        default => $paginate($filteredAttendance),
    };

    return $adminPage('reports', [
        'sports' => Sport::orderBy('name')->get(),
        'reportEvents' => Event::with('sport')->orderBy('title')->get(),
        'reportAthletes' => User::where('role', 'Student')->orderBy('name')->get(),
        'reportData' => $reportData,
        'reportType' => $reportType,
        'reportFilters' => $validated,
        'reportStats' => [
            'athletes' => $athletes->count(),
            'sports' => $sports->count(),
            'events' => $events->count(),
            'attendance' => $attendanceTotal,
            'present' => $statusCounts['Present'],
            'absent' => $statusCounts['Absent'],
            'late' => $statusCounts['Late'],
            'excused' => $statusCounts['Excused'],
            'rate' => $attendanceRate,
            'applications' => $applications->count(),
            'pendingApplications' => $applications->where('status', 'Pending')->count(),
            'approvedApplications' => $applications->where('status', 'Approved')->count(),
            'rejectedApplications' => $applications->where('status', 'Rejected')->count(),
        ],
        'statusCounts' => $statusCounts,
        'attendanceByMonth' => $attendanceByMonth,
        'athletesPerSport' => $athletesPerSport,
        'eventsPerSport' => $eventsPerSport,
        'lowAttendance' => $lowAttendance,
        'applicationBySport' => $applications->groupBy(fn ($application) => $application->sportCategory?->name ?? $application->sport ?? 'Unassigned')->map->count(),
        'generatedAt' => now(),
    ]);
})->middleware('auth')->name('reports.index');

Route::get('/reports/export', function () {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    request()->validate([
        'format' => ['required', 'in:csv'],
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date', 'after_or_equal:from'],
        'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
        'event_id' => ['nullable', 'integer', 'exists:events,id'],
        'athlete_id' => ['nullable', 'integer', 'exists:users,id'],
        'status' => ['nullable', 'in:Present,Late,Absent,Excused'],
    ]);

    $rows = Attendance::with(['athlete.sport', 'session.sport', 'event.sport'])
        ->when(request('from'), fn ($query, $value) => $query->whereDate('attended_on', '>=', $value))
        ->when(request('to'), fn ($query, $value) => $query->whereDate('attended_on', '<=', $value))
        ->when(request('sport_id'), fn ($query, $value) => $query->where(function ($nested) use ($value) {
            $nested->whereHas('athlete', fn ($athlete) => $athlete->where('sport_id', $value))
                ->orWhereHas('session', fn ($sessionQuery) => $sessionQuery->forSport($value));
        }))
        ->when(request('event_id'), fn ($query, $value) => $query->where('event_id', $value))
        ->when(request('athlete_id'), fn ($query, $value) => $query->where('user_id', $value))
        ->when(request('status'), fn ($query, $value) => $query->where('status', $value))
        ->latest('attended_on')->get();

    return response()->streamDownload(function () use ($rows) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['SNNHS Sports Hub - Attendance Report']);
        fputcsv($handle, ['Generated', now()->format('Y-m-d H:i')]);
        fputcsv($handle, []);
        fputcsv($handle, ['Date', 'Athlete', 'Student ID', 'Sport', 'Session', 'Venue', 'Status', 'Check-in']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->attended_on?->toDateString(),
                $row->athlete?->name,
                $row->athlete?->student_id,
                $row->sportName(),
                $row->displayName(),
                $row->venue(),
                $row->status,
                $row->check_in_time?->format('Y-m-d H:i:s'),
            ]);
        }
        fclose($handle);
    }, 'sportshub-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
})->middleware('auth')->name('reports.export');








