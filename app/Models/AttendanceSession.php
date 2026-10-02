<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'event_id',
        'sport_id',
        'title',
        'venue',
        'description',
        'session_date',
        'start_time',
        'end_time',
        'late_grace_minutes',
        'status',
        'created_by',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'late_grace_minutes' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(Attendance::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    /**
     * The sport that determines which athletes belong to this session.
     * A session may carry its own sport or inherit it from a linked event.
     */
    public function effectiveSportId(): ?int
    {
        if ($this->sport_id) {
            return (int) $this->sport_id;
        }

        return $this->event?->sport_id ? (int) $this->event->sport_id : null;
    }

    public function effectiveSportName(): string
    {
        return $this->sport?->name
            ?? $this->event?->sport?->name
            ?? 'All Sports';
    }

    public function displayName(): string
    {
        return $this->title ?: ($this->event?->title ?: 'Attendance Session');
    }

    public function scopeForSport(Builder $query, ?int $sportId): Builder
    {
        if (! $sportId) {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($sportId) {
            $nested->where('sport_id', $sportId)
                ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->where('sport_id', $sportId));
        });
    }

    /**
     * Restricts sessions to those the athlete is entitled to see, based on the
     * athlete's assigned sport. A session with no sport at all is an
     * "All Sports" session and is visible to every athlete.
     */
    public function scopeVisibleToAthlete(Builder $query, User $athlete): Builder
    {
        if (! $athlete->sport_id) {
            return $query->where(function (Builder $nested) use ($athlete) {
                $nested->whereNull('sport_id')
                    ->where(fn (Builder $inner) => $inner->whereNull('event_id')
                        ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->whereNull('sport_id')));
            });
        }

        return $query->where(function (Builder $nested) use ($athlete) {
            $nested->where('sport_id', $athlete->sport_id)
                ->orWhere(fn (Builder $sessionQuery) => $sessionQuery->whereNull('sport_id')
                    ->where(function (Builder $inner) use ($athlete) {
                        $inner->where(fn (Builder $unscoped) => $unscoped->whereNull('event_id')
                            ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->whereNull('sport_id')))
                            ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->where('sport_id', $athlete->sport_id));
                    }));
        });
    }
}
