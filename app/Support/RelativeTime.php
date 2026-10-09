<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Renders a timestamp as human readable relative time.
 *
 * Carbon already produces "1 minute ago", "5 minutes ago", "1 hour ago" and so
 * on. It reports anything under a minute as "0 seconds ago" / "12 seconds ago",
 * which reads poorly for something that was posted seconds earlier, so that
 * window is collapsed to "Just now".
 *
 * Kept in one place so announcements, notifications and any future feed share
 * identical wording, and so no template invents its own relative time.
 */
class RelativeTime
{
    /**
     * Diffs shorter than this are described as "Just now".
     */
    public const JUST_NOW_SECONDS = 60;

    public const UNAVAILABLE = 'Date unavailable';

    /**
     * Relative time for a timestamp, or a safe fallback when it is missing.
     */
    public static function of(?DateTimeInterface $moment, ?string $fallback = self::UNAVAILABLE): string
    {
        if (! $moment) {
            return $fallback;
        }

        $moment = $moment instanceof Carbon ? $moment : Carbon::instance($moment);

        return $moment->diffInSeconds() < self::JUST_NOW_SECONDS
            ? 'Just now'
            : $moment->diffForHumans();
    }

    /**
     * ISO 8601 value for a <time> element's datetime attribute, so the browser
     * can recompute the relative label on its own between page loads.
     */
    public static function machine(?DateTimeInterface $moment): ?string
    {
        if (! $moment) {
            return null;
        }

        $moment = $moment instanceof Carbon ? $moment : Carbon::instance($moment);

        return $moment->toIso8601String();
    }
}