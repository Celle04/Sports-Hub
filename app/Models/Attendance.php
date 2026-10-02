<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = ['attendance_session_id', 'event_id', 'user_id', 'status', 'attended_on', 'check_in_time'];

    protected function casts(): array
    {
        return ['attended_on' => 'date', 'check_in_time' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function athlete()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function wasCheckedIn(): bool
    {
        return in_array($this->status, ['Present', 'Late'], true);
    }

    public function displayName(): string
    {
        return $this->session?->displayName()
            ?? $this->event?->title
            ?? 'Attendance';
    }

    public function sportName(): string
    {
        return $this->session?->effectiveSportName()
            ?? $this->event?->sport?->name
            ?? $this->athlete?->sport?->name
            ?? 'All Sports';
    }

    public function venue(): ?string
    {
        return $this->session?->venue ?: $this->event?->venue;
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    /**
     * Excludes Pending expectations and cancelled sessions so rates reflect
     * attendance that actually happened.
     */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereIn('status', config('attendance.counted_statuses'))
            ->where(function (Builder $nested) {
                $nested->whereNull('attendance_session_id')
                    ->orWhereHas('session', fn (Builder $sessionQuery) => $sessionQuery->where('status', '!=', 'Cancelled'));
            });
    }

    public function scopeForSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('attendance_session_id', $sessionId);
    }

    public function scopeSearchAthlete(Builder $query, string $search): Builder
    {
        return $query->whereHas('athlete', function (Builder $athleteQuery) use ($search) {
            $athleteQuery->where('name', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
