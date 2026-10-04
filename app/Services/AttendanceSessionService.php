<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Notifications\AttendanceNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceSessionService
{
    /**
     * Athletes eligible for a session, derived entirely from the athlete's
     * assigned sport. No manual selection is involved anywhere in this class.
     */
    public function eligibleAthletes(AttendanceSession $session): Collection
    {
        $sportId = $session->effectiveSportId();

        return User::query()
            ->where('role', 'Student')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'Active'))
            ->when($sportId, fn ($query) => $query->where('sport_id', $sportId))
            ->orderBy('name')
            ->get();
    }

    /**
     * Creates a Pending record for every eligible athlete that does not yet
     * have one. Safe to call repeatedly; never overwrites existing statuses.
     */
    public function syncRoster(AttendanceSession $session): int
    {
        $session->loadMissing('event');

        $created = 0;

        foreach ($this->eligibleAthletes($session) as $athlete) {
            $created += Attendance::query()->firstOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'user_id' => $athlete->id,
                ],
                [
                    'event_id' => $session->event_id,
                    'status' => 'Pending',
                    'attended_on' => $session->session_date,
                ],
            )->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * Removes Pending records for athletes no longer eligible, so a roster
     * follows a sport reassignment without touching real attendance.
     */
    public function pruneRoster(AttendanceSession $session): int
    {
        $eligibleIds = $this->eligibleAthletes($session)->pluck('id')->all();

        return Attendance::query()
            ->forSession($session->id)
            ->where('status', 'Pending')
            ->when($eligibleIds === [], fn ($query) => $query->whereRaw('1 = 1'))
            ->when($eligibleIds !== [], fn ($query) => $query->whereNotIn('user_id', $eligibleIds))
            ->delete();
    }

    public function open(AttendanceSession $session): AttendanceSession
    {
        $session->loadMissing('event');
        $this->syncRoster($session);

        $session->update([
            'status' => 'Open',
            'opened_at' => now(),
            'closed_at' => null,
        ]);

        $this->notifyEligible($session, 'Attendance is now open for '.$session->displayName().'.', route('student.attendance'));

        return $session;
    }

    public function close(AttendanceSession $session): AttendanceSession
    {
        $session->loadMissing('event');
        $session->update(['status' => 'Closed', 'closed_at' => now()]);

        $this->notifyEligible($session, 'Attendance is now closed for '.$session->displayName().'.', route('student.attendance'));

        return $session;
    }

    public function cancel(AttendanceSession $session): AttendanceSession
    {
        $session->loadMissing('event');
        $session->update(['status' => 'Cancelled', 'closed_at' => now()]);

        $this->notifyEligible($session, $session->displayName().' has been cancelled.', route('student.attendance'));

        return $session;
    }

    /**
     * Athlete self check-in. Enforces ownership, sport eligibility, session
     * state and the single-record rule on the server side.
     */
    public function checkIn(AttendanceSession $session, User $athlete): array
    {
        abort_unless($athlete->role === 'Student', 403);

        return DB::transaction(function () use ($session, $athlete) {
            $locked = AttendanceSession::query()->with('event')->lockForUpdate()->findOrFail($session->id);

            abort_unless($locked->isOpen(), 422, 'This attendance session is not open for check-in.');
            abort_unless(! $locked->isCancelled(), 422, 'This attendance session has been cancelled.');
            abort_unless($locked->session_date?->isToday(), 422, 'Check-in is only available on the session date.');

            $sportId = $locked->effectiveSportId();
            abort_unless(
                $sportId && $athlete->sport_id && (int) $sportId === (int) $athlete->sport_id,
                403,
                'You are not eligible for this attendance session.'
            );

            $record = Attendance::query()
                ->forSession($locked->id)
                ->where('user_id', $athlete->id)
                ->lockForUpdate()
                ->first();

            if ($record && ! $record->isPending()) {
                return ['checkedIn' => false, 'record' => $record, 'label' => $locked->displayName()];
            }

            $status = $this->resolveCheckInStatus($locked, now());
            $checkedInAt = now();

            $record = $record ?: new Attendance([
                'attendance_session_id' => $locked->id,
                'event_id' => $locked->event_id,
                'user_id' => $athlete->id,
                'attended_on' => $locked->session_date,
            ]);

            $record->status = $status;
            $record->check_in_time = $checkedInAt;
            $record->save();

            return ['checkedIn' => true, 'record' => $record, 'label' => $locked->displayName(), 'time' => $checkedInAt];
        });
    }

    /**
     * Present up to the grace period after start time, Late afterwards.
     */
    public function resolveCheckInStatus(AttendanceSession $session, Carbon $moment): string
    {
        if (! $session->start_time) {
            return 'Present';
        }

        $startAt = Carbon::parse($session->session_date->toDateString().' '.$session->start_time);
        $grace = $session->late_grace_minutes ?? config('attendance.late_grace_minutes');

        return $moment->greaterThan($startAt->copy()->addMinutes((int) $grace)) ? 'Late' : 'Present';
    }

    public function notifyEligible(AttendanceSession $session, string $message, ?string $url = null): void
    {
        foreach ($this->eligibleAthletes($session) as $athlete) {
            $athlete->notify(new AttendanceNotification($message, $url));
        }
    }

    public function notifyAdministrators(string $message, ?string $url = null): void
    {
        foreach (User::query()->where('role', 'Administrator')->get() as $admin) {
            $admin->notify(new AttendanceNotification($message, $url));
        }
    }

    /**
     * Per-session status tallies for the admin roster.
     */
    public function sessionTally(AttendanceSession $session): array
    {
        $records = Attendance::query()->forSession($session->id)->get();

        return [
            'total' => $records->count(),
            'present' => $records->where('status', 'Present')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'pending' => $records->where('status', 'Pending')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
        ];
    }

    public function rateFor(User $athlete): float
    {
        $counted = Attendance::query()
            ->counted()
            ->where('user_id', $athlete->id);

        $total = (clone $counted)->count();
        $attended = (clone $counted)->whereIn('status', ['Present', 'Late'])->count();

        return $total > 0 ? round(($attended / $total) * 100, 1) : 0.0;
    }
}
