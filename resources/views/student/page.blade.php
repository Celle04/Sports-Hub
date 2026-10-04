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
        <details class="dashboard-notification-dropdown">
            <summary class="dashboard-notification-link" aria-label="Show notifications, {{ $dashboardUnreadCount }} unread">
                <svg class="dashboard-notification-icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
                <span>Notifications</span>
                <strong>{{ $dashboardUnreadCount }}</strong>
            </summary>
            <section class="dashboard-notification-panel" aria-label="All received updates">
                <header class="dashboard-notification-panel-heading">
                    <strong>All updates</strong>
                    <div>
                        <a class="text-link" href="{{ route('notifications.index') }}">Full inbox</a>
                        @if ($dashboardUnreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-link" type="submit">Mark all read</button></form>
                        @endif
                    </div>
                </header>
                <div class="dashboard-notification-list">
                    @forelse ($dashboardNotifications as $notification)
                        <article class="dashboard-notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                            <div>
                                <strong>{{ data_get($notification->data, 'title', 'Sports Hub update') }}</strong>
                                <span>{{ data_get($notification->data, 'message', 'You have a new update.') }}</span>
                                <small>{{ $notification->created_at?->diffForHumans() }}</small>
                            </div>
                            <div class="dashboard-notification-actions">
                                @if (data_get($notification->data, 'url'))
                                    <a class="text-link" href="{{ data_get($notification->data, 'url') }}">Open</a>
                                @endif
                                @if (! $notification->read_at)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="text-link" type="submit">Mark read</button></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="dashboard-notification-empty">No updates yet. New notifications will appear here.</p>
                    @endforelse
                </div>
            </section>
        </details>
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
