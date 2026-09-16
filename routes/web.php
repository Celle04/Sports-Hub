<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\SportController;
use App\Models\Application;
use App\Models\Event;
use App\Models\Sport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', function () {
    $credentials = request()->validate([
        'username' => ['required', 'email'],
        'password' => ['required', 'string'],
        'role' => ['required', 'in:Administrator,Student'],
    ]);

    if (auth()->attempt(['email' => $credentials['username'], 'password' => $credentials['password'], 'role' => $credentials['role']])) {
        request()->session()->regenerate();

        return redirect()->intended($credentials['role'] === 'Student' ? route('student.dashboard') : route('dashboard'));
    }

    return redirect()->route('login')->withErrors(['username' => 'The provided credentials do not match our records.'])->withInput(request()->only('username'));
})->name('login.submit');

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
    ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'student.calendar'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'icon' => 'calendar', 'route' => 'student.schedule'],
    ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'student.announcements'],
    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'student.attendance'],
    ['key' => 'coach', 'label' => 'Coach Info', 'icon' => 'users', 'route' => 'student.coach'],
    ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'user', 'route' => 'student.profile'],
];

$calendarData = function (?string $month = null): array {
    $calendarMonth = Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $calendarMonth->copy()->endOfMonth();
    $events = Event::where('status', 'Scheduled')->where('starts_at', '<=', $monthEnd)->where('ends_at', '>=', $monthStart)->orderBy('starts_at')->get();
    $calendarEvents = $events->groupBy(fn ($event) => $event->starts_at->toDateString());
    $days = array_fill(0, $monthStart->dayOfWeek, null);
    for ($day = 1; $day <= $monthEnd->day; $day++) {
        $days[] = $day;
    }

    return ['calendarMonth' => $calendarMonth, 'calendarDays' => $days, 'calendarEvents' => $calendarEvents];
};

$studentPage = function (string $page, array $data = []) use ($studentNav, $announcements) {
    $content = [
        'dashboard' => ['Dashboard', "Here's what's happening with your sports activities", null],
        'calendar' => ['Calendar', 'View your sports activities and important dates', null],
        'schedule' => ['My Schedule', 'Your upcoming training sessions and events', null],
        'announcements' => ['Announcements', 'Stay updated with the latest news and updates', null],
        'attendance' => ['Attendance Record', 'Track your attendance for training sessions and events', null],
        'coach' => ['Coach Information', 'Learn more about your coach', null],
        'profile' => ['My Profile', 'Manage your personal information', ['label' => 'Edit Profile', 'url' => '#']],
    ][$page];
    return view('student.page', array_merge(['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $studentNav, 'announcements' => $announcements, 'roleLabel' => 'Sports Hub', 'userName' => 'Juan Dela Cruz', 'userRole' => 'Basketball'], $data));
};

Route::get('/student', fn () => redirect()->route('student.dashboard'));
Route::get('/student/dashboard', fn () => $studentPage('dashboard'))->middleware('auth')->name('student.dashboard');
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
Route::get('/student/attendance', fn () => $studentPage('attendance'))->middleware('auth')->name('student.attendance');
Route::get('/student/coach', fn () => $studentPage('coach'))->middleware('auth')->name('student.coach');
Route::get('/student/profile', fn () => $studentPage('profile'))->middleware('auth')->name('student.profile');

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

$adminPage = function (string $page, array $data = []) use ($adminNav, $announcements) {
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

Route::get('/dashboard', fn () => $adminPage('dashboard', $calendarData()))->middleware('auth')->name('dashboard');
Route::get('/admin/calendar', fn () => $adminPage('calendar', $calendarData(request('month'))))->middleware('auth')->name('admin.calendar');
Route::get('/applications', fn () => $adminPage('applications', ['applications' => Application::latest()->get()]))->middleware('auth')->name('admin.applications');
Route::resource('sports', SportController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('auth');
Route::get('/athletes', fn () => $adminPage('athletes'))->middleware('auth')->name('athletes.index');
Route::get('/admin/medical', fn () => $adminPage('medical'))->middleware('auth')->name('admin.medical');
Route::get('/coaches', fn () => $adminPage('coaches'))->middleware('auth')->name('coaches.index');
Route::resource('events', EventController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('auth');
Route::get('/admin/attendance', fn () => $adminPage('attendance'))->middleware('auth')->name('admin.attendance');
Route::get('/admin/announcements', fn () => $adminPage('announcements'))->middleware('auth')->name('admin.announcements');
Route::get('/reports', fn () => $adminPage('reports'))->middleware('auth')->name('reports.index');
