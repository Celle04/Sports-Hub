<?php

namespace App\Support;

/**
 * The single source of truth for the portal navigation.
 *
 * Every admin and athlete screen renders the sidebar from these lists, so a
 * route is declared once and shows up on every page with the same label,
 * icon and grouping. The route names below are the ones the portal has always
 * used - the grouping is presentation only.
 */
class PortalNavigation
{
    /**
     * Navigation for the Admin Portal.
     *
     * @return array<int, array{key: string, label: string, icon: string, route: string, group: string}>
     */
    public static function admin(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'group' => 'Overview'],
            ['key' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'route' => 'sports.index', 'group' => 'Programs'],
            ['key' => 'athletes', 'label' => 'Athletes', 'icon' => 'activity', 'route' => 'athletes.index', 'group' => 'Programs'],
            ['key' => 'coaches', 'label' => 'Coaches', 'icon' => 'users', 'route' => 'coaches.index', 'group' => 'Programs'],
            ['key' => 'events', 'label' => 'Events', 'icon' => 'calendar', 'route' => 'events.index', 'group' => 'Operations'],
            ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'admin.calendar', 'group' => 'Operations'],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'admin.attendance', 'group' => 'Operations'],
            ['key' => 'applications', 'label' => 'Applications', 'icon' => 'clipboard', 'route' => 'admin.applications', 'group' => 'Records'],
            ['key' => 'medical', 'label' => 'Medical', 'icon' => 'medical', 'route' => 'admin.medical', 'group' => 'Records'],
            ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'admin.achievements', 'group' => 'Records'],
            ['key' => 'certificate-requests', 'label' => 'Certificate Requests', 'icon' => 'certificate', 'route' => 'admin.certificate-requests', 'group' => 'Records'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'route' => 'reports.index', 'group' => 'Insights'],
        ];
    }

    /**
     * Navigation for the Athlete/User Portal.
     *
     * @return array<int, array{key: string, label: string, icon: string, route: string, group: string}>
     */
    public static function student(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'student.dashboard', 'group' => 'Overview'],
            ['key' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'route' => 'student.sports', 'group' => 'My Sports'],
            ['key' => 'schedule', 'label' => 'My Schedule', 'icon' => 'calendar', 'route' => 'student.schedule', 'group' => 'My Sports'],
            ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'student.calendar', 'group' => 'My Sports'],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'student.attendance', 'group' => 'Records'],
            ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'student.achievements', 'group' => 'Records'],
            ['key' => 'application', 'label' => 'My Application', 'icon' => 'clipboard', 'route' => 'student.application', 'group' => 'Account'],
            ['key' => 'coach', 'label' => 'Coach Info', 'icon' => 'users', 'route' => 'student.coach', 'group' => 'Account'],
            ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'user', 'route' => 'student.profile', 'group' => 'Account'],
        ];
    }

    /**
     * The navigation that belongs to the signed in account.
     *
     * @return array<int, array{key: string, label: string, icon: string, route: string, group: string}>
     */
    public static function for(?string $role): array
    {
        return $role === 'Student' ? self::student() : self::admin();
    }
}
