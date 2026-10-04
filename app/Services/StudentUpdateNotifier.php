<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\StudentUpdateNotification;

class StudentUpdateNotifier
{
    public function notifyStudents(?array $sportIds, string $title, string $message, string $url): void
    {
        $students = User::query()->where('role', 'Student');

        if ($sportIds !== null) {
            $sportIds = array_values(array_unique($sportIds));

            if ($sportIds === []) {
                return;
            }

            $students->whereIn('sport_id', $sportIds);
        }

        $students->get()->each(fn (User $student) => $student->notify(new StudentUpdateNotification($title, $message, $url)));
    }

    public function notifyStudent(?User $student, string $title, string $message, string $url): void
    {
        if ($student?->role !== 'Student') {
            return;
        }

        $student->notify(new StudentUpdateNotification($title, $message, $url));
    }
}