<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An injury or medical incident recorded for an athlete.
 *
 * Incidents live beside the medical record rather than inside it so repeated
 * examinations never overwrite an injury history: the athlete keeps one log
 * that is shown inside their medical profile.
 */
class MedicalIncident extends Model
{
    /**
     * @var array<int, string>
     */
    public const SEVERITIES = ['Mild', 'Moderate', 'Severe'];

    /**
     * @var array<int, string>
     */
    public const CLEARANCES = ['Pending', 'Cleared', 'Not Cleared'];

    protected $fillable = [
        'athlete_id',
        'sport_id',
        'incident_date',
        'activity',
        'injury_type',
        'body_part',
        'severity',
        'description',
        'treatment',
        'rest_period_days',
        'return_to_play_date',
        'medical_clearance',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'return_to_play_date' => 'date',
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'athlete_id');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Incidents the athlete is still recovering from: not yet cleared and with
     * no return-to-play date, or one that has not arrived yet.
     *
     * @param  Builder<MedicalIncident>  $query
     * @return Builder<MedicalIncident>
     */
    public function scopeUnderRecovery(Builder $query): Builder
    {
        return $query
            ->where('medical_clearance', '!=', 'Cleared')
            ->where(function (Builder $recoveryQuery) {
                $recoveryQuery->whereNull('return_to_play_date')
                    ->orWhereDate('return_to_play_date', '>=', today()->toDateString());
            });
    }

    public function severityBadgeClass(): string
    {
        return 'badge medical-severity-'.str_replace(' ', '-', strtolower((string) $this->severity));
    }

    public function clearanceBadgeClass(): string
    {
        return 'badge medical-status-'.str_replace(' ', '-', strtolower((string) $this->medical_clearance));
    }

    public function sportName(): string
    {
        return $this->sport?->name ?? $this->athlete?->sport?->name ?? 'Unassigned';
    }

    public function incidentDateLabel(): string
    {
        return $this->incident_date?->format('M j, Y') ?? 'Not recorded';
    }

    public function returnToPlayLabel(): string
    {
        if ($this->return_to_play_date instanceof Carbon) {
            return $this->return_to_play_date->format('M j, Y');
        }

        return $this->medical_clearance === 'Cleared' ? 'Cleared' : 'Not scheduled';
    }

    public function restPeriodLabel(): string
    {
        if ($this->rest_period_days === null) {
            return 'Not set';
        }

        return $this->rest_period_days.' day'.((int) $this->rest_period_days === 1 ? '' : 's');
    }
}
