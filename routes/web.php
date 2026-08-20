<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', function () {
    if (request('role') === 'Student') {
        return redirect()->route('student.dashboard');
    }

    return redirect()->route('dashboard');
})->name('login.submit');

Route::get('/apply', function () {
    return view('application.create');
})->name('application.create');

Route::post('/apply', function () {
    return redirect()->route('application.create')->with('submitted', true);
})->name('application.store');

$announcements = [
    ['Basketball Tryouts This Friday', 'May 5, 2026', 'Basketball team tryouts will be held at the main court this Friday at 3:00 PM. All interested students are welcome to participate.'],
    ['Regional Sports Meet - June 2026', 'May 1, 2026', 'SNNHS will host the Regional Sports Meet in June. Athletes are encouraged to intensify their training sessions.'],
    ['New Sports Equipment Available', 'April 28, 2026', 'The school has acquired new training equipment for volleyball and track and field.'],
];

$studentNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => '&#8962;', 'route' => 'student.dashboard'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'icon' => '&#128197;', 'route' => 'student.schedule'],
    ['key' => 'announcements', 'label' => 'Announcements', 'icon' => '&#128227;', 'route' => 'student.announcements'],
    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => '&#9745;', 'route' => 'student.attendance'],
    ['key' => 'coach', 'label' => 'Coach Info', 'icon' => '&#9813;', 'route' => 'student.coach'],
    ['key' => 'profile', 'label' => 'My Profile', 'icon' => '&#9673;', 'route' => 'student.profile'],
];

$studentPage = function (string $page) use ($studentNav, $announcements) {
    $content = [
        'dashboard' => ['Dashboard', "Here's what's happening with your sports activities", null],
        'schedule' => ['My Schedule', 'Your upcoming training sessions and events', null],
        'announcements' => ['Announcements', 'Stay updated with the latest news and updates', null],
        'attendance' => ['Attendance Record', 'Track your attendance for training sessions and events', null],
        'coach' => ['Coach Information', 'Learn more about your coach', null],
        'profile' => ['My Profile', 'Manage your personal information', ['label' => 'Edit Profile', 'url' => '#']],
    ][$page];
    return view('student.page', ['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $studentNav, 'announcements' => $announcements, 'roleLabel' => 'Sports Hub', 'userName' => 'Juan Dela Cruz', 'userRole' => 'Basketball']);
};

Route::get('/student', fn () => redirect()->route('student.dashboard'));
Route::get('/student/dashboard', fn () => $studentPage('dashboard'))->name('student.dashboard');
Route::get('/student/schedule', fn () => $studentPage('schedule'))->name('student.schedule');
Route::get('/student/announcements', fn () => $studentPage('announcements'))->name('student.announcements');
Route::get('/student/attendance', fn () => $studentPage('attendance'))->name('student.attendance');
Route::get('/student/coach', fn () => $studentPage('coach'))->name('student.coach');
Route::get('/student/profile', fn () => $studentPage('profile'))->name('student.profile');

$adminNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => '&#8962;', 'route' => 'dashboard'],
    ['key' => 'applications', 'label' => 'Applications', 'icon' => '&#9745;', 'route' => 'admin.applications'],
    ['key' => 'sports', 'label' => 'Sports', 'icon' => '&#127942;', 'route' => 'sports.index'],
    ['key' => 'athletes', 'label' => 'Athletes', 'icon' => '&#127939;', 'route' => 'athletes.index'],
    ['key' => 'coaches', 'label' => 'Coaches', 'icon' => '&#9813;', 'route' => 'coaches.index'],
    ['key' => 'events', 'label' => 'Events', 'icon' => '&#128197;', 'route' => 'events.index'],
    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => '&#9745;', 'route' => 'admin.attendance'],
    ['key' => 'announcements', 'label' => 'Announcements', 'icon' => '&#128227;', 'route' => 'admin.announcements'],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => '&#128202;', 'route' => 'reports.index'],
];

$adminPage = function (string $page) use ($adminNav, $announcements) {
    $content = [
        'dashboard' => ['Admin Dashboard', 'Overview of SNNHS Sports Activity Hub', null],
        'applications' => ['Applications Management', 'Review and process athlete applications', null],
        'sports' => ['Sports Management', 'Manage sports programs and categories', 'Add New Sport'],
        'athletes' => ['Athlete Management', 'Manage registered athlete accounts', null],
        'coaches' => ['Coach Management', 'Manage coach records and assignments', 'Add New Coach'],
        'events' => ['Event Scheduling', 'Manage training sessions and competitions', 'Create Event'],
        'attendance' => ['Attendance Management', 'Track and record athlete attendance', null],
        'announcements' => ['Announcement Management', 'Create and manage public announcements', 'Create Announcement'],
        'reports' => ['Reports', 'Generate and download reports', null],
    ][$page];
    return view('admin.page', ['page' => $page, 'heading' => $content[0], 'subtitle' => $content[1], 'action' => $content[2], 'active' => $page, 'navItems' => $adminNav, 'announcements' => $announcements, 'roleLabel' => 'Admin Portal', 'userName' => 'Admin User', 'userRole' => 'Administrator']);
};

Route::get('/dashboard', fn () => $adminPage('dashboard'))->name('dashboard');
Route::get('/applications', fn () => $adminPage('applications'))->name('admin.applications');
Route::get('/sports', fn () => $adminPage('sports'))->name('sports.index');
Route::get('/athletes', fn () => $adminPage('athletes'))->name('athletes.index');
Route::get('/coaches', fn () => $adminPage('coaches'))->name('coaches.index');
Route::get('/events', fn () => $adminPage('events'))->name('events.index');
Route::get('/admin/attendance', fn () => $adminPage('attendance'))->name('admin.attendance');
Route::get('/admin/announcements', fn () => $adminPage('announcements'))->name('admin.announcements');
Route::get('/reports', fn () => $adminPage('reports'))->name('reports.index');
