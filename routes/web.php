<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CertificateRequestController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MedicalController;
use App\Http\Controllers\MedicalIncidentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SportController;
use App\Models\Achievement;
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
use App\Support\PortalNavigation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('landing', [
        'sports' => Sport::orderBy('name')->get(),
        'announcements' => collect(),
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

        if ($credentials['role'] === 'Student' && $user->needsPasswordChange()) {
            return redirect()->route('student.change-password');
        }

        return redirect()->intended($credentials['role'] === 'Student' ? route('student.dashboard') : route('dashboard'));
    }

    return redirect()->route('login')->withErrors(['username' => 'Invalid email or password.'])->withInput(request()->only('username', 'role'));
})->name('login.submit');

// Password reset runs entirely through Laravel's password broker: the broker
// creates and hashes the token, delivers the branded notification, enforces
// the 60 minute expiry and the per-user resend throttle, and invalidates the
// token once it has been used. The throttles below add per-IP rate limiting.
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:5,1')
    ->name('password.email');

Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset');

Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:5,1')
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

/*
 * PortalNavigation is the single source of truth for both portals, so the
 * grouped sidebar never drifts between modules. The group key is presentation
 * only: every route name below is unchanged.
 */
$studentNav = PortalNavigation::student();

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
        'attendance' => ['Attendance Record', 'Track your attendance for training sessions and events', null],
        'achievements' => ['My Achievements', 'Your medals, awards and accomplishments', null],
        'application' => ['My Application', 'View your sports application status and feedback', null],
        'coach' => ['Coach Information', 'Learn more about your coach', null],
        'profile' => ['My Profile', 'Manage your personal information', ['label' => 'Edit Profile', 'url' => '#']],
    ][$page];

    return view('student.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $studentNav,          'studentApplication' => $studentApplication, 'upcomingStudentEvents' => $upcomingStudentEvents, 'athlete' => $athlete, 'roleLabel' => 'Athlete/User Portal', 'userName' => $athlete->name, 'userRole' => $athlete->sport?->name ?? 'Unassigned sport'], $dashboardData, $data));
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

Route::middleware(['force.password.change'])->group(function () use ($studentPage, $studentNav, $calendarData, $studentAchievementData) {
    Route::get('/student/achievements', fn () => $studentAchievementData())->middleware('auth')->name('student.achievements');
Route::get('/student/achievements/{achievement}', fn (int $achievement) => $studentAchievementData($achievement))->middleware('auth')->name('student.achievements.show');
Route::get('/student/achievements/{achievement}/certificate', function (int $achievement) {
    $student = auth()->user();
    $record = Achievement::query()->forAthlete($student)->with('sport')->findOrFail($achievement);

    /*
     * The certificate is only available once an administrator generated it
     * and marked it ready for release, and only for the athlete who owns
     * the achievement.
     */
    $certificateRequest = CertificateRequest::query()
        ->forStudent($student)
        ->where('achievement_id', $record->id)
        ->where('status', CertificateRequest::STATUS_ISSUED)
        ->latest('handled_at')
        ->first();

    /*
     * A certificate is available once an administrator generated it and marked
     * it ready for release. Records that were issued before the workflow
     * existed keep their own file, so nothing that was already handed out
     * disappears.
     */
    $file = $certificateRequest?->isGenerated()
        ? $certificateRequest->certificate_path
        : ($record->hasCertificate() && ! $record->certificateRequests()->whereNotNull('certificate_path')->exists()
            ? $record->certificate_path
            : null);

    abort_unless($file, 404, 'Your certificate is not available yet.');

    return Storage::disk('private')->download(
        $file,
        $record->certificateFileName(),
    );
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
Route::get('/student/sports', function () use ($studentPage) {
    $sports = Sport::where('status', 'Active')->with('coaches')->orderBy('name')->get();

    $sports->each(function (Sport $sport): void {
        $forSportApplications = static fn ($query) => $query->where(static fn ($sportApplications) => $sportApplications
            ->where('sport_id', $sport->id)
            ->orWhere(static fn ($legacyApplications) => $legacyApplications
                ->whereNull('sport_id')
                ->where('sport', $sport->name)));

        $sport->members_count = User::query()
            ->where('role', 'Student')
            ->where('status', 'Active')
            ->where('sport_id', $sport->id)
            ->whereDoesntHave('applications', static fn ($query) => $forSportApplications($query)->where('status', '!=', 'Approved'))
            ->count();
    });

    return $studentPage('sports', ['studentSports' => $sports]);
})->middleware('auth')->name('student.sports');
Route::get('/student/sports/{sport}/members', [SportController::class, 'studentMembers'])->middleware('auth')->name('student.sports.members');
Route::get('/student/sports/{sport}/members/{athlete}', [SportController::class, 'studentMemberProfile'])->middleware('auth')->name('student.sports.member-profile');
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
Route::get('/student/change-password', [ChangePasswordController::class, 'show'])->middleware('auth')->name('student.change-password');
Route::post('/student/change-password', [ChangePasswordController::class, 'update'])->middleware('auth')->name('student.change-password.update');
});

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

$adminNav = PortalNavigation::admin();

Route::get('/notifications', function () use ($studentNav, $adminNav) {
    $user = auth()->user();
    $isStudent = $user->role === 'Student';

    return view('notifications.index', [
        'notifications' => $user->notifications()->latest()->simplePaginate(20),
        'title' => 'Notifications',
        'navItems' => PortalNavigation::for($user->role),
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
        'coaches' => ['Coach Management', 'Manage coach records and their sport assignments', 'Add New Coach'],
        'events' => ['Event Scheduling', 'Manage training sessions and competitions', 'Create Event'],
        'attendance' => ['Attendance Management', 'Track and record athlete attendance', null],
        'reports' => ['Reports', 'Generate and download reports', null],
    ][$page];

    return view('admin.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $adminNav, 'roleLabel' => 'Admin Portal', 'userName' => auth()->user()->name, 'userRole' => 'Administrator'], $data));
};

Route::get('/dashboard', function () use ($adminPage) {
    $today = today();

    /*
     * Every figure on the command centre is resolved once here and then reused
     * by the metric cards and the panels, so a single aggregate query backs
     * each number instead of the same count being re-run per card.
     */
    $athleteCount = User::where('role', 'Student')->count();
    $activeAthleteCount = User::where('role', 'Student')->where('status', 'Active')->count();
    $sportCount = Sport::count();
    $activeSportCount = Sport::where('status', 'Active')->count();
    $upcomingEventCount = Event::where('status', 'Scheduled')->where('starts_at', '>=', now())->count();
    $todaySessionCount = AttendanceSession::whereDate('session_date', $today)->count();
    $openSessionCount = AttendanceSession::whereDate('session_date', $today)->where('status', 'Open')->count();
    $pendingApplicationCount = Application::where('status', 'Pending')->count();
    $recordedAchievementCount = Achievement::count();
    $medalCounts = Achievement::query()
        ->whereIn('achievement_type', Achievement::MEDAL_TYPES)
        ->selectRaw('achievement_type, COUNT(*) as total')
        ->groupBy('achievement_type')
        ->pluck('total', 'achievement_type');
    $medalCount = (int) $medalCounts->sum();

    $attendanceToday = Attendance::whereDate('attended_on', $today)
        ->whereNotNull('attendance_session_id')
        ->whereIn('status', config('attendance.counted_statuses'))
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');
    $countedToday = (int) $attendanceToday->sum();
    $presentTodayCount = (int) $attendanceToday->get('Present', 0) + (int) $attendanceToday->get('Late', 0);
    $attendanceRate = $countedToday > 0
        ? round($presentTodayCount / $countedToday * 100, 1)
        : 0;
    $pendingCheckInCount = Attendance::whereDate('attended_on', $today)
        ->where('status', 'Pending')
        ->whereNotNull('attendance_session_id')
        ->count();

    $medicalStatusCounts = MedicalRecord::query()
        ->selectRaw('medical_status, COUNT(*) as total')
        ->groupBy('medical_status')
        ->pluck('total', 'medical_status');
    $clearedAthletes = (int) ($medicalStatusCounts->get('Cleared') ?? 0);

    $athletesMissingMedical = User::where('role', 'Student')->whereDoesntHave('medicalRecords')->count();
    $checkupsDue = MedicalRecord::whereNotNull('next_checkup_date')
        ->whereBetween('next_checkup_date', [$today, $today->copy()->addDays(30)])
        ->count();
    $pendingCertificateRequests = CertificateRequest::where('status', CertificateRequest::STATUS_PENDING)->count();

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

    /*
     * The metric cards. Every value comes from the counts resolved above, so
     * the command centre never shows a figure the database cannot back.
     */
    $metrics = [
        [
            'value' => $athleteCount,
            'label' => 'Total Athletes',
            'hint' => 'Active Athletes: '.$activeAthleteCount,
            'action' => 'View athletes',
            'route' => 'athletes.index',
            'icon' => 'users',
            'tone' => 'primary',
        ],
        [
            'value' => $sportCount,
            'label' => 'Total Sports',
            'hint' => $activeSportCount.' active program'.($activeSportCount === 1 ? '' : 's'),
            'action' => 'View programs',
            'route' => 'sports.index',
            'icon' => 'trophy',
            'tone' => 'accent',
        ],
        [
            'value' => $upcomingEventCount,
            'label' => 'Upcoming Events',
            'hint' => 'Scheduled from today onward',
            'action' => 'View events',
            'route' => 'events.index',
            'icon' => 'calendar',
            'tone' => 'info',
        ],
        [
            'value' => $todaySessionCount,
            'label' => "Today's Sessions",
            'hint' => $openSessionCount.' still open for check-in',
            'action' => 'Take attendance',
            'route' => 'admin.attendance',
            'icon' => 'clipboard',
            'tone' => 'success',
        ],
        [
            'value' => $pendingApplicationCount,
            'label' => 'Pending Applications',
            'hint' => 'Waiting for a decision',
            'action' => 'Review queue',
            'route' => 'admin.applications',
            'icon' => 'bell',
            'tone' => 'warning',
        ],
        [
            'value' => $recordedAchievementCount,
            'label' => 'Total Achievements',
            'hint' => 'Recorded across all sports',
            'action' => 'View achievements',
            'route' => 'admin.achievements',
            'icon' => 'certificate',
            'tone' => 'primary',
        ],
        [
            'value' => $medalCount,
            'label' => 'Medals Awarded',
            'hint' => 'Gold, silver and bronze',
            'action' => 'View achievements',
            'route' => 'admin.achievements',
            'icon' => 'trophy',
            'tone' => 'warning',
        ],
        [
            'value' => $attendanceRate.'%',
            'label' => 'Attendance Rate',
            'hint' => $presentTodayCount.' present today',
            'action' => 'Full report',
            'route' => 'reports.index',
            'icon' => 'chart',
            'tone' => 'success',
        ],
    ];

    return $adminPage('dashboard', [
        'metrics' => $metrics,
        'sportsStats' => [
            'athletes' => $athleteCount,
            'sports' => $sportCount,
            'achievements' => $recordedAchievementCount,
            'gold' => (int) $medalCounts->get('Gold Medal', 0),
            'silver' => (int) $medalCounts->get('Silver Medal', 0),
            'bronze' => (int) $medalCounts->get('Bronze Medal', 0),
        ],
        'achievementsBySport' => $achievementsBySport,
        'recentAchievements' => $recentAchievements,
        'upcomingEvents' => Event::with('sport')->where('status', 'Scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
        'athletesBySport' => Sport::withCount(['athletes' => fn ($query) => $query->where('role', 'Student')])->orderBy('name')->get(),
        'medicalStatusCounts' => [
            'Cleared' => (int) ($medicalStatusCounts->get('Cleared') ?? 0),
            'Pending' => (int) ($medicalStatusCounts->get('Pending') ?? 0),
            'Not Cleared' => (int) ($medicalStatusCounts->get('Not Cleared') ?? 0),
            'Restricted' => (int) ($medicalStatusCounts->get('Restricted') ?? 0),
        ],
        'attendanceToday' => [
            'counted' => $countedToday,
            'present' => (int) $attendanceToday->get('Present', 0),
            'late' => (int) $attendanceToday->get('Late', 0),
            'absent' => (int) $attendanceToday->get('Absent', 0),
            'excused' => (int) $attendanceToday->get('Excused', 0),
            'pending' => $pendingCheckInCount,
            'rate' => $attendanceRate,
            'sessions' => $todaySessionCount,
            'openSessions' => $openSessionCount,
        ],
        'medicalClearance' => [
            'cleared' => $clearedAthletes,
            'athletes' => $athleteCount,
        ],
        'pendingApplications' => Application::with('sportCategory')->where('status', 'Pending')->latest()->limit(5)->get(),
        'attentionItems' => collect([
            $pendingApplicationCount ? ['tone' => 'warning', 'message' => $pendingApplicationCount.' application(s) waiting for review', 'route' => 'admin.applications', 'action' => 'Review'] : null,
            $athletesMissingMedical ? ['tone' => 'danger', 'message' => $athletesMissingMedical.' athlete(s) need medical clearance', 'route' => 'admin.medical', 'action' => 'View'] : null,
            $checkupsDue ? ['tone' => 'warning', 'message' => $checkupsDue.' medical checkup(s) due within 30 days', 'route' => 'admin.medical', 'action' => 'View'] : null,
            $pendingCertificateRequests ? ['tone' => 'warning', 'message' => $pendingCertificateRequests.' certificate request(s) waiting for approval', 'route' => 'admin.certificate-requests', 'action' => 'Review'] : null,
        ])->filter()->values(),
        'recentActivity' => collect([
            ...User::where('role', 'Student')->latest('created_at')->limit(2)->get()->map(fn ($athlete) => ['label' => $athlete->name.' was added as an athlete', 'date' => $athlete->created_at])->all(),
            ...Event::with('sport')->latest('created_at')->limit(2)->get()->map(fn ($event) => ['label' => $event->title.' was created', 'date' => $event->created_at])->all(),
            ...Application::latest('created_at')->limit(2)->get()->map(fn ($application) => ['label' => $application->name.' submitted a sports application', 'date' => $application->created_at])->all(),
            ...MedicalRecord::with('athlete')->latest('updated_at')->limit(2)->get()->map(fn ($record) => ['label' => 'Medical record updated'.($record->athlete?->name ? ' for '.$record->athlete->name : ''), 'date' => $record->updated_at])->all(),
            ...AttendanceSession::latest('created_at')->limit(2)->get()->map(fn ($session) => ['label' => 'Attendance session created for '.$session->effectiveSportName(), 'date' => $session->created_at])->all(),
            ...Achievement::with('athlete')->latest('created_at')->limit(2)->get()->map(fn ($achievement) => ['label' => $achievement->title.' awarded to '.($achievement->athlete?->name ?? 'an athlete'), 'date' => $achievement->created_at])->all(),
        ])->sortByDesc('date')->take(5)->values(),
    ]);
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

Route::post('/athletes/{athlete}/password-reset', [AthleteController::class, 'resetPassword'])
    ->middleware('auth')->name('athletes.password.reset');

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
Route::post('/admin/achievements/{achievement}/certificate/issue', [AchievementController::class, 'issueCertificate'])->middleware('auth')->name('admin.achievements.certificate.issue');
Route::get('/admin/certificate-requests', [CertificateRequestController::class, 'index'])->middleware('auth')->name('admin.certificate-requests');
Route::post('/admin/certificate-requests/{certificateRequest}/approve', [CertificateRequestController::class, 'approve'])->middleware('auth')->name('admin.certificate-requests.approve');
Route::post('/admin/certificate-requests/{certificateRequest}/generate', [CertificateRequestController::class, 'generate'])->middleware('auth')->name('admin.certificate-requests.generate');
Route::post('/admin/certificate-requests/{certificateRequest}/release', [CertificateRequestController::class, 'release'])->middleware('auth')->name('admin.certificate-requests.release');
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
Route::get('/admin/attendance/sessions/{session}/export/pdf', [AttendanceController::class, 'exportPdf'])->middleware('auth')->name('admin.attendance.sessions.export.pdf');
Route::put('/admin/attendance/records/{attendance}', [AttendanceController::class, 'updateRecord'])->middleware('auth')->name('admin.attendance.records.update');

Route::get('/reports', [ReportController::class, 'index'])->middleware('auth')->name('reports.index');

Route::get('/reports/export', [ReportController::class, 'export'])->middleware('auth')->name('reports.export');
