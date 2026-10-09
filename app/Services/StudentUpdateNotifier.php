<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\StudentUpdateNotification;

class StudentUpdateNotifier
{
    /**
     * Notify the active student athletes behind a set of sports.
     *
     * @param  array<int, int>|null  $sportIds  Null reaches every active athlete.
     * @param  array<string, mixed>  $meta  Optional payload stored with the
     *                                   notification. A `dedupe_key` entry makes
     *                                   the send idempotent: a user who already
     *                                   holds a notification with that key is
     *                                   skipped, so re-saving a record or
     *                                   double-submitting a form can never
     *                                   stack up duplicate entries.
     */
    public function notifyStudents(?array $sportIds, string $title, string $message, string $url, array $meta = []): void
    {
        $students = User::query()->where('role', 'Student')->where('status', 'Active');

        if ($sportIds !== null) {
            $sportIds = array_values(array_unique(array_filter($sportIds)));

            if ($sportIds === []) {
                return;
            }

            $students->where(fn ($query) => $query
                ->whereIn('sport_id', $sportIds)
                ->orWhereHas('applications', fn ($applications) => $applications
                    ->where('status', 'Approved')
                    ->whereIn('sport_id', $sportIds)));
        }

        $dedupeKey = $meta['dedupe_key'] ?? null;

        $students->get()
            ->reject(fn (User $student) => $dedupeKey !== null && $student->notifications()
                ->where('data->dedupe_key', $dedupeKey)
                ->exists())
            ->each(fn (User $student) => $student->notify(new StudentUpdateNotification($title, $message, $url, $meta)));
    }

    public function notifyStudent(?User $student, string $title, string $message, string $url, array $meta = []): void
    {
        if ($student?->role !== 'Student') {
            return;
        }

        $dedupeKey = $meta['dedupe_key'] ?? null;

        if ($dedupeKey !== null && $student->notifications()->where('data->dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        $student->notify(new StudentUpdateNotification($title, $message, $url, $meta));
    }
}