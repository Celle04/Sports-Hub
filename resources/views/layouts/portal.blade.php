<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SNNHS SportsHub' }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
    <script>
        (function () {
            try {
                var root = document.documentElement;
                if (localStorage.getItem('sports_hub_theme') === 'dark') {
                    root.setAttribute('data-theme', 'dark');
                }
                if (localStorage.getItem('sports_hub_sidebar') === 'collapsed') {
                    root.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <svg class="icon-sprite" aria-hidden="true">
        <symbol id="icon-dashboard" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z" /></symbol>
        <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></symbol>
        <symbol id="icon-clipboard" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4.5V3h6v1.5M8 10h8M8 14h6" /></symbol>
        <symbol id="icon-megaphone" viewBox="0 0 24 24"><path d="m3 11 15-5v12L3 14v-3Z" /><path d="M18 10h2a2 2 0 0 1 0 4h-2M6 15l1 5" /></symbol>
        <symbol id="icon-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3" /><path d="M3 20a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5" /></symbol>
        <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3" /><path d="M5 21a7 7 0 0 1 14 0" /></symbol>
        <symbol id="icon-trophy" viewBox="0 0 24 24"><path d="M8 4h8v5a4 4 0 0 1-8 0V4ZM12 13v4M8 21h8M9 17h6M8 6H4v2a4 4 0 0 0 4 4M16 6h4v2a4 4 0 0 1-4 4" /></symbol>
        <symbol id="icon-activity" viewBox="0 0 24 24"><path d="m3 12 4-4 4 8 4-8 3 4h3" /></symbol>
        <symbol id="icon-medical" viewBox="0 0 24 24"><path d="M9 3h6v5h5v6h-5v5H9v-5H4V8h5V3Z" /></symbol>
        <symbol id="icon-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></symbol>
        <symbol id="icon-logout" viewBox="0 0 24 24"><path d="M10 5H5v14h5M14 8l4 4-4 4M9 12h9" /></symbol>
        <symbol id="icon-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></symbol>
        <symbol id="icon-pin" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></symbol>
        <symbol id="icon-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></symbol>
        <symbol id="icon-eye-off" viewBox="0 0 24 24"><path d="m3 3 18 18" /><path d="M10.6 10.7a2 2 0 0 0 2.8 2.8" /><path d="M9.9 4.3A10.6 10.6 0 0 1 12 4c6.4 0 10 8 10 8a17.6 17.6 0 0 1-2.3 3.2M6.6 6.7A17.4 17.4 0 0 0 2 12s3.6 8 10 8a10.4 10.4 0 0 0 4.3-.9" /></symbol>
        <symbol id="icon-panel-left" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M9 4v16" /></symbol>
        <symbol id="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></symbol>
        <symbol id="icon-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" /></symbol>
        <symbol id="icon-chevron-left" viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7" /></symbol>
        <symbol id="icon-arrow-left" viewBox="0 0 24 24"><path d="M20 12H4M11 19l-7-7 7-7" /></symbol>
        <symbol id="icon-chevron-right" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" /></symbol>
<symbol id="icon-certificate" viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /><path d="M12 16.5l-1.4.9.5-1.7-1.4-1 1.7-.1.6-1.7.6 1.7 1.7.1-1.4 1 .5 1.7-1.4-.9Z" /></symbol>
        <symbol id="icon-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></symbol>
        <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></symbol>
        <symbol id="icon-filter" viewBox="0 0 24 24"><path d="M4 5h16M7 12h10M10 19h4" /></symbol>
        <symbol id="icon-more-horizontal" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></symbol>
        <symbol id="icon-trash" viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13M9 7V4h6v3" /></symbol>
        <symbol id="icon-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5" /></symbol>
        <symbol id="icon-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12" /></symbol>
        <symbol id="icon-warning" viewBox="0 0 24 24"><path d="M12 3 2 21h20L12 3Z" /><path d="M12 9v4M12 17h.01" /></symbol>
        <symbol id="icon-file" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6V3Z" /><path d="M14 3v4h4" /></symbol>
    </svg>
    <div class="portal">
        @php
            /*
             * The navigation is grouped for the sidebar. The grouping is purely
             * presentational: every route name below is the one the portal has
             * always used.
             */
            $navGroups = collect($navItems ?? [])->groupBy('group');
            $portalUser = auth()->user();
            $portalHome = $homeUrl ?? ($portalUser?->role === 'Student' ? route('student.dashboard') : route('dashboard'));
            $portalProfileUrl = $portalUser?->role === 'Student' ? route('student.profile') : null;
            $portalTopbarTitle = $topbarTitle ?? (isset($page) ? \Illuminate\Support\Str::headline($page) : ($title ?? 'SportsHub'));
        @endphp
        <aside class="portal-sidebar" id="portal-sidebar">
            <button type="button" class="portal-sidebar-close" data-sidebar-close aria-label="Close navigation">
                <svg aria-hidden="true"><use href="#icon-x"></use></svg>
            </button>
            <div class="portal-sidebar-head">
                <a class="portal-brand" href="{{ $portalHome }}">
                    <span class="portal-logo-tile"><img class="portal-logo" src="{{ asset('images/snnhs logo.png') }}" alt="SNNHS logo"></span>
                    <span class="portal-brand-text">
                        <strong>SportsHub</strong>
                        <small>{{ $roleLabel ?? 'SportsHub' }}</small>
                    </span>
                </a>
                <button type="button" class="portal-sidebar-collapse" data-sidebar-toggle aria-label="Collapse sidebar" aria-expanded="true" aria-controls="portal-sidebar" data-tooltip="Collapse sidebar">
                    <svg class="nav-icon" aria-hidden="true"><use href="#icon-panel-left"></use></svg>
                </button>
            </div>

            <nav class="portal-nav" aria-label="Portal navigation">
                @foreach ($navGroups as $groupLabel => $groupItems)
                    <div class="portal-nav-group">
                        <span class="portal-nav-group-label">{{ $groupLabel }}</span>
                        @foreach ($groupItems as $item)
                            <a class="{{ ($active ?? null) === $item['key'] ? 'active' : '' }}" href="{{ route($item['route']) }}" data-tooltip="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" {{ ($active ?? null) === $item['key'] ? 'aria-current="page"' : '' }}>
                                <svg class="nav-icon" aria-hidden="true"><use href="#icon-{{ $item['icon'] }}"></use></svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="portal-sidebar-foot">
                <div class="user-chip">
                    @auth
                        <a class="user-chip-link" href="{{ $portalProfileUrl ?? $portalHome }}">
                            <span class="user-chip-body">
                                <x-avatar :user="auth()->user()" size="sm" decorative class="user-chip-avatar" />
                                <span class="user-chip-text">
                                    {{ $userName ?? auth()->user()->name }}
                                    <small>{{ $userRole ?? auth()->user()->role }}</small>
                                </span>
                            </span>
                        </a>
                    @else
                        <div class="user-chip-body">
                            <span class="avatar avatar--sm avatar-initials user-chip-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($userName ?? 'Juan Dela Cruz', 0, 2)) }}</span>
                            <span class="user-chip-text">
                                {{ $userName ?? 'Juan Dela Cruz' }}
                                <small>{{ $userRole ?? 'Basketball' }}</small>
                            </span>
                        </div>
                    @endauth
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="portal-logout" type="submit" data-tooltip="Logout" aria-label="Logout"><svg class="nav-icon" aria-hidden="true"><use href="#icon-logout"></use></svg><span>Logout</span></button>
                </form>
            </div>
        </aside>

        <div class="portal-sidebar-backdrop" data-sidebar-backdrop></div>

        <main class="portal-main">
            <div class="portal-content">
            <header class="portal-topbar">
                <div class="portal-topbar-start">
                    <button type="button" class="portal-icon-button portal-sidebar-open" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false" aria-controls="portal-sidebar" data-tooltip="Open navigation">
                        <svg aria-hidden="true"><use href="#icon-panel-left"></use></svg>
                    </button>
                    <nav class="portal-breadcrumb" aria-label="Breadcrumb">
                        <span class="portal-breadcrumb-context">{{ $roleLabel ?? 'SportsHub' }}</span>
                        <span class="portal-breadcrumb-separator" aria-hidden="true">/</span>
                        <strong class="portal-breadcrumb-current">{{ $portalTopbarTitle }}</strong>
                    </nav>
                </div>
                <div class="portal-topbar-actions">
                    <button type="button" class="portal-icon-button" id="theme-toggle" aria-label="Dark Mode" aria-pressed="false" data-tooltip="Dark Mode">
                        <svg class="theme-icon theme-icon-sun" aria-hidden="true"><use href="#icon-sun"></use></svg>
                        <svg class="theme-icon theme-icon-moon" aria-hidden="true"><use href="#icon-moon"></use></svg>
                    </button>
            @auth
                @php
                    $notificationUser = auth()->user();
                    $unreadNotificationCount = $notificationUser->unreadNotifications()->count();
                    $badgeCount = $unreadNotificationCount > 99 ? '99+' : (string) $unreadNotificationCount;
                    $recentNotifications = $notificationUser->notifications()->latest()->limit(10)->get();
                @endphp
                <div class="portal-notifications" aria-label="Notifications">
                    <button type="button" class="portal-notification-trigger" id="portal-notification-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="portal-notification-menu" data-notification-toggle>
                        <svg class="portal-notification-icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
                        <span class="portal-notification-label">Notifications</span>
                        @if ($unreadNotificationCount > 0)
                            <span class="portal-notification-badge" aria-label="{{ $badgeCount }} unread notifications">{{ $badgeCount }}</span>
                        @endif
                    </button>
                    <div id="portal-notification-menu" class="portal-notification-menu" role="menu" aria-labelledby="portal-notification-trigger" hidden>
                        <header class="portal-notification-header">
                            <div class="portal-notification-header-info">
                                <h2 class="portal-notification-header-title">Notifications</h2>
                                @if ($unreadNotificationCount > 0)
                                    <span class="portal-notification-header-count">{{ $unreadNotificationCount }} unread</span>
                                @endif
                            </div>
                            <div class="portal-notification-header-actions">
                                @if ($unreadNotificationCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}" class="portal-notification-mark-all">
                                        @csrf
                                        <button class="portal-notification-mark-all-btn" type="submit" title="Mark all as read"><svg class="portal-notification-mark-all-icon" aria-hidden="true"><use href="#icon-check"></use></svg><span class="portal-notification-mark-all-label">Mark all as read</span></button>
                                    </form>
                                @endif
                                <button type="button" class="portal-notification-close" data-notification-close aria-label="Close notifications"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
                            </div>
                        </header>
                        <div class="portal-notification-list" role="menu">
                            @forelse ($recentNotifications as $notification)
                                <article class="portal-notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}" role="menuitem">
                                    <div class="portal-notification-icon-wrap"><svg aria-hidden="true"><use href="#icon-bell"></use></svg></div>
                                    <div class="portal-notification-copy">
                                        <div class="portal-notification-title-row">
                                            <strong class="portal-notification-title">{{ data_get($notification->data, 'title', 'SportsHub update') }}</strong>
                                            @if (! $notification->read_at)
                                                <span class="portal-notification-unread-dot" aria-hidden="true"></span>
                                            @endif
                                        </div>
                                        <p class="portal-notification-message">{{ data_get($notification->data, 'message', 'You have a new update.') }}</p>
                                        <small class="portal-notification-time relative-time" data-posted-at="{{ \App\Support\RelativeTime::machine($notification->created_at) }}">{{ \App\Support\RelativeTime::of($notification->created_at) }}</small>
                                    </div>
                                    <div class="portal-notification-actions">
                                        @if (data_get($notification->data, 'url'))
                                            <a class="text-link portal-notification-open" href="{{ route('notifications.open', $notification->id) }}">Open</a>
                                        @endif
                                        @if (! $notification->read_at)
                                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="portal-notification-mark-read">
                                                @csrf
                                                <button class="text-link" type="submit">Mark as read</button>
                                            </form>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="portal-notification-empty">
                                    <span class="portal-notification-empty-icon" aria-hidden="true"><svg><use href="#icon-bell"></use></svg></span>
                                    <strong class="portal-notification-empty-title">You're all caught up.</strong>
                                    <p class="portal-notification-empty-subtitle">New updates will appear here.</p>
                                </div>
                            @endforelse
                        </div>
                        <footer class="portal-notification-footer">
                            <a class="text-link" href="{{ route('notifications.index') }}">View all notifications</a>
                        </footer>
                    </div>
                </div>

                @if ($portalProfileUrl)
                    <a class="portal-topbar-profile" href="{{ $portalProfileUrl }}">
                        <span class="portal-topbar-profile-text">
                            <strong>{{ $userName ?? auth()->user()->name }}</strong>
                            <small>{{ $roleLabel ?? auth()->user()->role }}</small>
                        </span>
                        <x-avatar :user="auth()->user()" size="sm" decorative class="portal-topbar-avatar" />
                    </a>
                @else
                    <div class="portal-topbar-profile">
                        <span class="portal-topbar-profile-text">
                            <strong>{{ $userName ?? auth()->user()->name }}</strong>
                            <small>{{ $roleLabel ?? auth()->user()->role }}</small>
                        </span>
                        <x-avatar :user="auth()->user()" size="sm" decorative class="portal-topbar-avatar" />
                    </div>
                @endif
            @endauth
                </div>
            </header>

                @yield('content')
            </div>
        </main>
    </div>
    @include('partials.confirm-dialog')
    <script src="{{ asset('js/offline.js') }}" defer></script>
    <script src="{{ asset('js/filters.js') }}" defer></script>
    <script src="{{ asset('js/profile.js') }}" defer></script>
    <script src="{{ asset('js/relative-time.js') }}" defer></script>
    <script src="{{ asset('js/app-review.js') }}" defer></script>
    <script src="{{ asset('js/confirm-delete.js') }}" defer></script>
    <script src="{{ asset('js/password-reset.js') }}" defer></script>
    <script src="{{ asset('js/achievement-form.js') }}" defer></script>
    <script src="{{ asset('js/portal-ui.js') }}" defer></script>
</body>
</html>
