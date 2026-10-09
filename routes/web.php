<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CertificateRequestController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MedicalController;
use App\Http\Controllers\MedicalIncidentController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SportController;
use App\Models\Achievement;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\CertificateRequest;
use App\Models\Event;
use App\Models\MedicalRecord;
use App\Models\Sport;
use App\Models\User;
use App\Notifications\CertificateRequestNotification;
use App\Services\AttendanceSessionService;
use App\Services\StudentUpdateNotifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('landing', [
        'sports' => Sport::orderBy('name')->get(),
        'announcements' => Announcement::with('sport')->published()->orderByDesc('published_at')->orderByDesc('id')->get(),
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

Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');

Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])
    ->middleware('throttle:5,1')
    ->name('password.email');

Route::get('/forgot-password/verify', [PasswordResetController::class, 'showVerifyForm'])->name('password.otp.verify');

Route::post('/forgot-password/verify', [PasswordResetController::class, 'verifyOtp'])
    ->middleware('throttle:10,1')
    ->name('password.otp.check');

Route::post('/forgot-password/resend', [PasswordResetController::class, 'resendOtp'])
    ->middleware('throttle:5,1')
    ->name('password.otp.resend');

Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])
    ->middleware('otp.verified')
    ->name('password.reset');

Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('otp.verified')
    ->name('password.update');

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

$studentNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'student.dashboard'],
    ['key' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'route' => 'student.sports'],
    ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'student.calendar'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'icon' => 'calendar', 'route' => 'student.schedule'],
    ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'student.announcements'],
    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'student.attendance'],
    ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'student.achievements'],
    ['key' => 'application', 'label' => 'My Application', 'icon' => 'clipboard', 'route' => 'student.application'],
    ['key' => 'coach', 'label' => 'Coach Info', 'icon' => 'users', 'route' => 'student.coach'],
    ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'user', 'route' => 'student.profile'],
];

$calendarData = function (?string $month = null): array {
    $monthValue = $month ?: request('month', now()->format('Y-m'));
    $calendarMonth = Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $calendarMonth->copy()->endOfMonth();

    $studentSportIds = auth()->user()?->role === 'Student' ? auth()->user()->sportIds() : null;

    $eventsQuery = Event::with(['sport', 'coach'])
        ->when($studentSportIds !== null, fn ($query) => $query->where('status', 'Scheduled'))
        ->when(request('sport_id'), fn ($query, $sportId) => $query->where('sport_id', $sportId))
        ->when(request('venue'), fn ($query, $venue) => $query->where('venue', $venue))
        ->when(request('event_type'), fn ($query, $eventType) => $query->where('event_type', $eventType))
        ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
        ->where(function ($query) use ($monthStart, $monthEnd) {
            $query->whereBetween('starts_at', [$monthStart, $monthEnd])
                ->orWhereBetween('ends_at', [$monthStart, $monthEnd]);
        })
        ->when($studentSportIds !== null, function ($query) use ($studentSportIds) {
            $query->where(function ($sportQuery) use ($studentSportIds) {
                $sportQuery->whereNull('sport_id');
                if ($studentSportIds !== []) {
                    $sportQuery->orWhereIn('sport_id', $studentSportIds);
                }
            });
        });

    $events = $eventsQuery->orderBy('starts_at')->get();

    // Build the six week grid first so events can be clamped to it.
    $days = [];
    $dayCursor = $calendarMonth->copy()->startOfWeek(Carbon::SUNDAY);
    $lastDay = $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
    while ($dayCursor->lte($lastDay)) {
        $days[] = $dayCursor->copy();
        $dayCursor->addDay();
    }

    $gridStart = $days[0]->copy()->startOfDay();
    $gridEnd = $days[count($days) - 1]->copy()->endOfDay();

    // Place an event on every day it covers, not only on its start date, so a
    // tournament that runs from the 30th into the 2nd is visible on all of its
    // days. The span is clamped to the visible grid so an event with a distant
    // end date cannot expand into thousands of entries.
    $placements = collect();
    foreach ($events as $event) {
        $spanStart = $event->starts_at->copy()->startOfDay()->max($gridStart);
        $spanEnd = ($event->ends_at ?? $event->starts_at)->copy()->endOfDay()->min($gridEnd);

        for ($day = $spanStart->copy(); $day->lte($spanEnd); $day->addDay()) {
            $placements->push(['day' => $day->toDateString(), 'event' => $event]);
        }
    }

    $calendarEvents = $placements
        ->sortBy(fn (array $placement) => $placement['event']->starts_at->timestamp)
        ->groupBy('day')
        ->map(fn ($dayPlacements) => $dayPlacements->pluck('event')->values());

    $studentEvents = Event::query()
        ->when($studentSportIds !== null, function ($query) use ($studentSportIds) {
            $query->where('status', 'Scheduled')->where(function ($sportQuery) use ($studentSportIds) {
                $sportQuery->whereNull('sport_id');
                if ($studentSportIds !== []) {
                    $sportQuery->orWhereIn('sport_id', $studentSportIds);
                }
            });
        });

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
        'statuses' => EventController::statuses(),
        'upcomingEvents' => Event::with('sport')->where('status', 'Scheduled')->where('starts_at', '>=', now())->when($studentSportIds !== null, function ($query) use ($studentSportIds) {
            $query->where(function ($sportQuery) use ($studentSportIds) {
                $sportQuery->whereNull('sport_id');
                if ($studentSportIds !== []) {
                    $sportQuery->orWhereIn('sport_id', $studentSportIds);
                }
            });
        })->orderBy('starts_at')->limit(5)->get(),
    ];
};

$studentPage = function (string $page, array $data = []) use ($studentNav) {
    abort_unless(auth()->user()?->role === 'Student', 403);
    $athlete = auth()->user();
    $publishedAnnouncements = Announcement::with('sport')->visibleTo($athlete)->orderByDesc('published_at')->orderByDesc('id')->get();
    $studentApplication = $athlete->applications()->with('sportCategory')->latest()->first();
    $studentSportIds = $athlete->sportIds();
    $upcomingStudentEventsQuery = Event::with(['sport', 'coach'])
        ->where('status', 'Scheduled')
        ->where('starts_at', '>=', now())
        ->where(function ($query) use ($studentSportIds) {
            $query->whereNull('sport_id');
            if ($studentSportIds !== []) {
                $query->orWhereIn('sport_id', $studentSportIds);
            }
        })
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

        $dashboardAchievements = $athlete->achievements()
            ->with('sport')
            ->orderByDesc('date_achieved')
            ->orderByDesc('id')
            ->get();

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
            'dashboardAchievementCount' => $dashboardAchievements->count(),
            'dashboardMedalCount' => $dashboardAchievements->filter(fn ($achievement) => $achievement->isMedal())->count(),
            'dashboardRecentAchievements' => $dashboardAchievements->take(3),
        ];
    }
    $content = [
        'dashboard' => ['Dashboard', "Here's what's happening with your sports activities", null],
        'sports' => ['Sports Programs', 'View sports programs managed by SNNHS', null],
        'calendar' => ['Calendar', 'View your sports activities and important dates', null],
        'schedule' => ['My Schedule', 'Your upcoming training sessions and events', null],
        'announcements' => ['Announcements', 'Stay updated with the latest news and updates', null],
        'attendance' => ['Attendance Record', 'Track your attendance for training sessions and events', null],
        'achievements' => ['My Achievements', 'Your medals, awards and accomplishments', null],
        'application' => ['My Application', 'View your sports application status and feedback', null],
        'coach' => ['Coach Information', 'Learn more about your coach', null],
        'profile' => ['My Profile', 'Manage your personal information', ['label' => 'Edit Profile', 'url' => '#']],
    ][$page];

    return view('student.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $studentNav,          'announcements' => $publishedAnnouncements, 'studentApplication' => $studentApplication, 'upcomingStudentEvents' => $upcomingStudentEvents, 'athlete' => $athlete, 'roleLabel' => 'Athlete/User Portal', 'userName' => $athlete->name, 'userRole' => $athlete->sport?->name ?? 'Unassigned sport'], $dashboardData, $data));
};

$announcementIsVisible = fn (Announcement $announcement): bool => $announcement->isVisible();

/**
 * Push an announcement notification to the athletes it was aimed at.
 *
 * The send is idempotent: every notification is stamped with a dedupe key of
 * "<announcement id>|<reason>", so an athlete can never end up with two copies
 * of the same announcement event no matter how often the admin re-saves, opens
 * or re-publishes the post.
 */
$notifyStudentsAboutAnnouncement = function (Announcement $announcement, string $title, string $message, string $reason = 'published', ?array $sportIds = null) {
    $sportIds ??= $announcement->sport_id ? [$announcement->sport_id] : null;
    $dedupeKey = $announcement->id.'|'.$reason;

    app(StudentUpdateNotifier::class)->notifyStudents(
        $sportIds,
        $title,
        $message,
        route('student.announcements', ['announcement' => $announcement->id]),
        [
            'announcement_id' => $announcement->id,
            'announcement_title' => $announcement->title,
            'dedupe_key' => $dedupeKey,
        ],
    );
};

/**
 * Data every student achievements page renders with.
 *
 * The records are resolved from the authenticated account only. No achievement
 * id supplied by the browser is ever used to pick the set, so an athlete can
 * never widen the result to somebody else's rows.
 */
$studentAchievementData = function (?int $selectedId = null) use ($studentPage) {
    $athlete = auth()->user();
    abort_unless($athlete?->role === 'Student', 403);

    $achievements = $athlete->achievements()
        ->with(['sport', 'event'])
        ->orderByDesc('date_achieved')
        ->orderByDesc('id')
        ->get();

    $selectedAchievement = null;

    if ($selectedId !== null) {
        // Scoped lookup: the URL id is matched against this athlete's own rows,
        // so another athlete's achievement resolves to a 404 instead of a page.
        $selectedAchievement = Achievement::query()
            ->forAthlete($athlete)
            ->with(['sport', 'event'])
            ->findOrFail($selectedId);
    }

    // Latest certificate request per achievement, so the module can show a
    // request button or the current request status without leaving the page.
    $certificateRequests = CertificateRequest::query()
        ->forStudent($athlete)
        ->whereIn('achievement_id', $achievements->pluck('id'))
        ->orderBy('id')
        ->get()
        ->keyBy('achievement_id');

    return $studentPage('achievements', [
        'heading' => $selectedAchievement?->title ?? 'My Achievements',
        'subtitle' => $selectedAchievement
            ? $selectedAchievement->sportLabel().' · '.$selectedAchievement->dateAchievedLabel()
            : 'Your medals, awards and accomplishments',
        'achievements' => $achievements,
        'achievementSummary' => [
            'total' => $achievements->count(),
            'medals' => $achievements->filter(fn ($achievement) => $achievement->isMedal())->count(),
            'titles' => $achievements->whereIn('achievement_type', ['Champion', 'Runner-up'])->count(),
            'sports' => $achievements->pluck('sport_id')->filter()->unique()->count(),
        ],
        'achievementTimeline' => $achievements->groupBy(fn ($achievement) => $achievement->year()),
        'certificateRequests' => $certificateRequests,
        'selectedAchievement' => $selectedAchievement,
        'achievementViewMode' => $selectedAchievement ? 'show' : 'index',
    ]);
};

Route::get('/student/achievements', fn () => $studentAchievementData())->middleware('auth')->name('student.achievements');
Route::get('/student/achievements/{achievement}', fn (int $achievement) => $studentAchievementData($achievement))->middleware('auth')->name('student.achievements.show');
Route::get('/student/achievements/{achievement}/certificate', function (int $achievement) {
    $record = Achievement::query()->forAthlete(auth()->user())->with('sport')->findOrFail($achievement);

    abort_unless($record->hasCertificate(), 404, 'No certificate is attached to this achievement.');

    return Storage::disk('private')->download($record->certificate_path, $record->certificateDiskName());
})->middleware('auth')->name('student.achievements.certificate');

Route::post('/student/achievements/{achievement}/certificate-request', function (int $achievement) {
    $student = auth()->user();
    abort_unless($student?->role === 'Student', 403);

    // The lookup goes through the forAthlete scope, so the student can only
    // ever request a certificate for one of their own achievements.
    $record = Achievement::query()->forAthlete($student)->findOrFail($achievement);

    if ($record->hasCertificate()) {
        return back()->withErrors(['certificate' => 'This achievement already has a certificate attached.']);
    }

    $openRequestExists = CertificateRequest::query()
        ->forStudent($student)
        ->where('achievement_id', $record->id)
        ->whereIn('status', [CertificateRequest::STATUS_PENDING, CertificateRequest::STATUS_APPROVED])
        ->exists();

    if ($openRequestExists) {
        return back()->withErrors(['certificate' => 'You already have a pending certificate request for this achievement.']);
    }

    $request = CertificateRequest::create([
        'achievement_id' => $record->id,
        'student_id' => $student->id,
        'status' => CertificateRequest::STATUS_PENDING,
    ]);

    foreach (User::where('role', 'Administrator')->get() as $admin) {
        $admin->notify(new CertificateRequestNotification(
            'New Certificate Request',
            $student->name.' requested a certificate for "'.$record->title.'".',
            route('admin.certificate-requests'),
            ['dedupe_key' => 'certificate-request|submitted|'.$request->id],
        ));
    }

    return back()->with('success', 'Your certificate request has been submitted for approval.');
})->middleware('auth')->name('student.achievements.certificate-request');

Route::get('/student', fn () => redirect()->route('student.dashboard'));
Route::get('/student/dashboard', fn () => $studentPage('dashboard'))->middleware('auth')->name('student.dashboard');
Route::get('/student/sports', fn () => $studentPage('sports', ['studentSports' => Sport::where('status', 'Active')->with('coaches')->orderBy('name')->get()]))->middleware('auth')->name('student.sports');
Route::get('/student/calendar', fn () => $studentPage('calendar', $calendarData(request('month'))))->middleware('auth')->name('student.calendar');
Route::get('/student/schedule', fn () => $studentPage('schedule', [
    'events' => Event::where('status', 'Scheduled')
        ->where('starts_at', '>=', now())
        ->where(function ($query) {
            $sportIds = auth()->user()->sportIds();
            $query->whereNull('sport_id');
            if ($sportIds !== []) {
                $query->orWhereIn('sport_id', $sportIds);
            }
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
Route::patch('/student/profile', [ProfileController::class, 'update'])->middleware('auth')->name('student.profile.update');
Route::patch('/student/profile/password', [ProfileController::class, 'updatePassword'])->middleware('auth')->name('student.profile.password');

Route::post('/notifications/{notification}/read', function (string $notification) {
    abort_unless(auth()->check(), 403);
    $record = auth()->user()->notifications()->whereKey($notification)->firstOrFail();
    $record->markAsRead();

    return back();
})->middleware('auth')->name('notifications.read');

/**
 * Open a notification: marks it read and forwards the user to whatever the
 * notification points at. Scoping the lookup to the signed in user's own
 * notifications means one athlete can never open or clear another's.
 */
Route::get('/notifications/{notification}', function (string $notification) {
    abort_unless(auth()->check(), 403);
    $record = auth()->user()->notifications()->whereKey($notification)->firstOrFail();
    $target = data_get($record->data, 'url') ?: route('notifications.index');
    $record->markAsRead();

    return redirect()->to($target);
})->middleware('auth')->name('notifications.open');

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
    ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'admin.achievements'],
    ['key' => 'certificate-requests', 'label' => 'Certificate Requests', 'icon' => 'certificate', 'route' => 'admin.certificate-requests'],
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
        'roleLabel' => $isStudent ? 'Athlete/User Portal' : 'Admin Portal',
        'userName' => $user->name,
        'userRole' => $user->sport?->name ?? $user->role,
        'homeUrl' => $isStudent ? route('student.dashboard') : route('dashboard'),
    ]);
})->middleware('auth')->name('notifications.index');

$adminPage = function (string $page, array $data = []) use ($adminNav) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);
    $content = [
        'dashboard' => ['Admin Dashboard', 'Overview of SNNHS SportsHub', null],
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

    return view('admin.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $adminNav, 'roleLabel' => 'Admin Portal', 'userName' => 'Admin User', 'userRole' => 'Administrator'], $data));
};

Route::get('/dashboard', function () use ($adminPage, $calendarData) {
    $countedToday = Attendance::whereDate('attended_on', today())
        ->whereNotNull('attendance_session_id')
        ->whereIn('status', config('attendance.counted_statuses'))
        ->get();
    $attendanceRate = $countedToday->count() > 0
        ? round($countedToday->whereIn('status', ['Present', 'Late'])->count() / $countedToday->count() * 100, 1)
        : 0;

    $registeredAthletes = User::where('role', 'Student')->count();
    $registeredSports = Sport::count();
    $recordedAchievements = Achievement::count();
    $medalCounts = Achievement::query()
        ->whereIn('achievement_type', Achievement::MEDAL_TYPES)
        ->selectRaw('achievement_type, COUNT(*) as total')
        ->groupBy('achievement_type')
        ->pluck('total', 'achievement_type');
    $achievementsBySport = Achievement::query()
        ->select('sport_id')
        ->selectRaw('COUNT(*) as total')
        ->whereNotNull('sport_id')
        ->with('sport:id,name')
        ->groupBy('sport_id')
        ->orderByDesc('total')
        ->get();
    $recentAchievements = Achievement::with(['athlete:id,name,student_id', 'sport:id,name', 'event:id,title'])
        ->orderByDesc('date_achieved')
        ->orderByDesc('id')
        ->limit(6)
        ->get();

    return $adminPage('dashboard', array_merge($calendarData(), [
        'sportsStats' => [
            'athletes' => $registeredAthletes,
            'sports' => $registeredSports,
            'achievements' => $recordedAchievements,
            'gold' => (int) $medalCounts->get('Gold Medal', 0),
            'silver' => (int) $medalCounts->get('Silver Medal', 0),
            'bronze' => (int) $medalCounts->get('Bronze Medal', 0),
        ],
        'achievementsBySport' => $achievementsBySport,
        'recentAchievements' => $recentAchievements,
        'stats' => [
            ['value' => Sport::count(), 'label' => 'Total Sports', 'action' => 'View programs', 'route' => 'sports.index', 'icon' => 'trophy', 'tone' => 'red'],
            ['value' => User::where('role', 'Student')->count(), 'label' => 'Active Athletes', 'action' => 'View athletes', 'route' => 'athletes.index', 'icon' => 'users', 'tone' => 'green'],
            ['value' => Sport::whereHas('athletes', fn ($query) => $query->where('role', 'Student'))->count(), 'label' => 'Sports With Athletes', 'action' => 'View sports', 'route' => 'sports.index', 'icon' => 'activity', 'tone' => 'gold'],
            ['value' => Event::where('status', 'Scheduled')->where('starts_at', '>=', now())->count(), 'label' => 'Upcoming Events', 'action' => 'View events', 'route' => 'events.index', 'icon' => 'calendar', 'tone' => 'red'],
            ['value' => AttendanceSession::whereDate('session_date', today())->count(), 'label' => "Today's Sessions", 'action' => 'View attendance', 'route' => 'admin.attendance', 'icon' => 'clipboard', 'tone' => 'green'],
            ['value' => Attendance::whereDate('attended_on', today())->whereIn('status', ['Present', 'Late'])->whereNotNull('attendance_session_id')->count(), 'label' => 'Present Today', 'action' => 'View attendance', 'route' => 'admin.attendance', 'icon' => 'user', 'tone' => 'gold'],
            ['value' => Attendance::whereDate('attended_on', today())->where('status', 'Pending')->whereNotNull('attendance_session_id')->count(), 'label' => 'Pending Check-ins', 'action' => 'Review pending', 'route' => 'admin.attendance', 'icon' => 'bell', 'tone' => 'red'],
            ['value' => $attendanceRate.'%', 'label' => 'Attendance Rate', 'action' => 'Full report', 'route' => 'reports.index', 'icon' => 'chart', 'tone' => 'green'],
            ['value' => Application::where('status', 'Pending')->count(), 'label' => 'Pending Applications', 'action' => 'Review applications', 'route' => 'admin.applications', 'icon' => 'clipboard', 'tone' => 'gold'],
            ['value' => MedicalRecord::where('medical_status', 'Cleared')->count().' / '.User::where('role', 'Student')->count(), 'label' => 'Medical Clearance', 'action' => 'View records', 'route' => 'admin.medical', 'icon' => 'medical', 'tone' => 'red'],
            ['value' => Achievement::query()->whereIn('achievement_type', ['Gold Medal', 'Silver Medal', 'Bronze Medal'])->count(), 'label' => 'Medals Awarded', 'action' => 'View achievements', 'route' => 'admin.achievements', 'icon' => 'trophy', 'tone' => 'gold'],
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
            CertificateRequest::where('status', CertificateRequest::STATUS_PENDING)->count() ? ['tone' => 'warning', 'message' => CertificateRequest::where('status', CertificateRequest::STATUS_PENDING)->count().' certificate request(s) waiting for approval', 'route' => 'admin.certificate-requests', 'action' => 'Review'] : null,
        ])->filter()->values(),
        'recentActivity' => collect([
            ...User::where('role', 'Student')->latest('created_at')->limit(2)->get()->map(fn ($athlete) => ['label' => $athlete->name.' was added as an athlete', 'date' => $athlete->created_at])->all(),
            ...Event::with('sport')->latest('created_at')->limit(2)->get()->map(fn ($event) => ['label' => $event->title.' was created', 'date' => $event->created_at])->all(),
            ...Application::latest('created_at')->limit(2)->get()->map(fn ($application) => ['label' => $application->name.' submitted a sports application', 'date' => $application->created_at])->all(),
            ...MedicalRecord::with('athlete')->latest('updated_at')->limit(2)->get()->map(fn ($record) => ['label' => 'Medical record updated'.($record->athlete?->name ? ' for '.$record->athlete->name : ''), 'date' => $record->updated_at])->all(),
            ...AttendanceSession::latest('created_at')->limit(2)->get()->map(fn ($session) => ['label' => 'Attendance session created for '.$session->effectiveSportName(), 'date' => $session->created_at])->all(),
            ...Achievement::with('athlete')->latest('created_at')->limit(2)->get()->map(fn ($achievement) => ['label' => $achievement->title.' awarded to '.($achievement->athlete?->name ?? 'an athlete'), 'date' => $achievement->created_at])->all(),
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
Route::get('/athletes/{athlete}', [AthleteController::class, 'show'])->middleware('auth')->name('athletes.show');
Route::get('/athletes/{athlete}/edit', [AthleteController::class, 'edit'])->middleware('auth')->name('athletes.edit');
Route::put('/athletes/{athlete}', [AthleteController::class, 'update'])->middleware('auth')->name('athletes.update');
Route::delete('/athletes/{athlete}', [AthleteController::class, 'destroy'])->middleware('auth')->name('athletes.destroy');
Route::get('/admin/medical', [MedicalController::class, 'index'])->middleware('auth')->name('admin.medical');
Route::get('/admin/medical/create', [MedicalController::class, 'create'])->middleware('auth')->name('admin.medical.create');
Route::post('/admin/medical', [MedicalController::class, 'store'])->middleware('auth')->name('admin.medical.store');
Route::post('/admin/medical/incidents', [MedicalIncidentController::class, 'store'])->middleware('auth')->name('admin.medical.incidents.store');
Route::put('/admin/medical/incidents/{incident}', [MedicalIncidentController::class, 'update'])->middleware('auth')->name('admin.medical.incidents.update');
Route::delete('/admin/medical/incidents/{incident}', [MedicalIncidentController::class, 'destroy'])->middleware('auth')->name('admin.medical.incidents.destroy');
Route::patch('/admin/medical/{medical}/archive', [MedicalController::class, 'archive'])->middleware('auth')->name('admin.medical.archive');
Route::patch('/admin/medical/{medical}/restore', [MedicalController::class, 'restore'])->middleware('auth')->name('admin.medical.restore');
Route::get('/admin/medical/{medical}/certificate/view', [MedicalController::class, 'viewCertificate'])->middleware('auth')->name('admin.medical.certificate.view');
Route::get('/admin/medical/{medical}', [MedicalController::class, 'show'])->middleware('auth')->name('admin.medical.show');
Route::get('/admin/medical/{medical}/edit', [MedicalController::class, 'edit'])->middleware('auth')->name('admin.medical.edit');
Route::put('/admin/medical/{medical}', [MedicalController::class, 'update'])->middleware('auth')->name('admin.medical.update');
Route::delete('/admin/medical/{medical}', [MedicalController::class, 'destroy'])->middleware('auth')->name('admin.medical.destroy');
Route::get('/admin/medical/{medical}/certificate', [MedicalController::class, 'downloadCertificate'])->middleware('auth')->name('admin.medical.certificate');

Route::get('/admin/achievements', [AchievementController::class, 'index'])->middleware('auth')->name('admin.achievements');
Route::get('/admin/achievements/create', [AchievementController::class, 'create'])->middleware('auth')->name('admin.achievements.create');
Route::post('/admin/achievements', [AchievementController::class, 'store'])->middleware('auth')->name('admin.achievements.store');
Route::get('/admin/achievements/certificate', [AchievementController::class, 'generate'])->middleware('auth')->name('admin.achievements.certificate.generate');
Route::get('/admin/achievements/{achievement}/certificate/print', [AchievementController::class, 'renderCertificate'])->middleware('auth')->name('admin.achievements.certificate.print');
Route::get('/admin/achievements/{achievement}/certificate/pdf', [AchievementController::class, 'downloadCertificate'])->middleware('auth')->name('admin.achievements.certificate.pdf');
Route::get('/admin/achievements/{achievement}', [AchievementController::class, 'show'])->middleware('auth')->name('admin.achievements.show');
Route::get('/admin/achievements/{achievement}/edit', [AchievementController::class, 'edit'])->middleware('auth')->name('admin.achievements.edit');
Route::put('/admin/achievements/{achievement}', [AchievementController::class, 'update'])->middleware('auth')->name('admin.achievements.update');
Route::delete('/admin/achievements/{achievement}', [AchievementController::class, 'destroy'])->middleware('auth')->name('admin.achievements.destroy');
Route::get('/admin/achievements/{achievement}/certificate', [AchievementController::class, 'certificate'])->middleware('auth')->name('admin.achievements.certificate');
Route::get('/admin/certificate-requests', [CertificateRequestController::class, 'index'])->middleware('auth')->name('admin.certificate-requests');
Route::post('/admin/certificate-requests/{certificateRequest}/approve', [CertificateRequestController::class, 'approve'])->middleware('auth')->name('admin.certificate-requests.approve');
Route::post('/admin/certificate-requests/{certificateRequest}/issue', [CertificateRequestController::class, 'issue'])->middleware('auth')->name('admin.certificate-requests.issue');
Route::post('/admin/certificate-requests/{certificateRequest}/reject', [CertificateRequestController::class, 'reject'])->middleware('auth')->name('admin.certificate-requests.reject');
Route::resource('coaches', CoachController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])->middleware('auth');
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

    if (! $wasVisible && $isVisible) {
        $notifyStudentsAboutAnnouncement($announcement, 'New announcement', $announcement->title.' was published.');
    } elseif ($wasVisible && ! $isVisible) {
        $notifyStudentsAboutAnnouncement(
            $announcement,
            'Announcement update',
            $announcement->title.' is no longer available.',
            'withdrawn',
            $previousSportId ? [$previousSportId] : null,
        );
    } elseif ($wasVisible && $previousSportId !== $announcement->sport_id) {
        // The audience was retargeted while live: welcome the athletes who can
        // now read it and let the previous audience know it left their feed.
        if ($announcement->sport_id) {
            $notifyStudentsAboutAnnouncement($announcement, 'New announcement', $announcement->title.' was published.');
        }

        if ($previousSportId) {
            $notifyStudentsAboutAnnouncement(
                $announcement,
                'Announcement update',
                $announcement->title.' is no longer available.',
                'withdrawn',
                [$previousSportId],
            );
        }
    }

    return redirect()->route('admin.announcements', ['announcement_id' => $announcement->id])->with('success', 'Announcement updated successfully.');
})->middleware('auth')->name('admin.announcements.update');

Route::delete('/admin/announcements/{announcement}', function (Announcement $announcement) use ($announcementIsVisible, $notifyStudentsAboutAnnouncement) {
    abort_unless(auth()->user()?->role === 'Administrator', 403);

    if ($announcementIsVisible($announcement)) {
        $notifyStudentsAboutAnnouncement($announcement, 'Announcement update', $announcement->title.' is no longer available.', 'withdrawn');
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
        $notifyStudentsAboutAnnouncement($announcement, 'Announcement update', $announcement->title.' has been archived.', 'withdrawn');
    }

    return back()->with('success', 'Announcement archived successfully.');
})->middleware('auth')->name('admin.announcements.archive');
Route::get('/reports', [ReportController::class, 'index'])->middleware('auth')->name('reports.index');

Route::get('/reports/export', [ReportController::class, 'export'])->middleware('auth')->name('reports.export');
