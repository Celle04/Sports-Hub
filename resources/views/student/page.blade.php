@extends('layouts.portal')

@section('content')
<div class="module-header">
    <div>
        @if ($page === 'dashboard')
            <h1>Welcome back, {{ $athlete->name }}!</h1>
            <p class="page-subtitle">Here's your sports activity overview.</p>
        @else
            <h1>{{ $heading }}</h1>
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($page === 'dashboard')
        <a class="dashboard-notification-link" href="#portal-notifications" aria-label="Notifications: {{ $dashboardUnreadCount }} unread">
            <svg class="dashboard-notification-icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
            <span>Notifications</span>
            <strong>{{ $dashboardUnreadCount }}</strong>
        </a>
    @endif
</div>

@if (session('success'))
    <div class="notice">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="notice">Please correct the highlighted form fields.</div>
@endif

@if (in_array($page, ['dashboard', 'sports', 'calendar', 'schedule', 'announcements', 'attendance', 'application', 'coach', 'profile'], true))
    @include('student.modules.'.$page)
@else
    <div class="card"><p>Use the navigation to access this section.</p></div>
@endif
@endsection
