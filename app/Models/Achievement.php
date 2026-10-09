<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Achievement extends Model
{
    /**
     * The achievement categories the Sports Coordinator can pick from.
     *
     * Stored as plain text rather than in a lookup table because the list is
     * short, fixed, and the coordinator can still type a custom value: the form
     * input is backed by this list but is not restricted to it.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'Gold Medal',
        'Silver Medal',
        'Bronze Medal',
        'Champion',
        'Runner-up',
        'MVP',
        'Best Player',
        'Best Athlete',
        'Participation',
        'Qualification',
        'Award',
        'Other',
    ];

    /**
     * Types that are displayed with a medal colour chip.
     *
     * @var array<int, string>
     */
    public const MEDAL_TYPES = ['Gold Medal', 'Silver Medal', 'Bronze Medal'];

    protected $fillable = [
        'athlete_id',
        'sport_id',
        'event_id',
        'title',
        'description',
        'achievement_type',
        'competition',
        'place',
        'date_achieved',
        'certificate_path',
    ];

    protected function casts(): array
    {
        return [
            'date_achieved' => 'date',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope that keeps an athlete to their own record set.
     *
     * The athlete side of the module resolves records through this scope using
     * the authenticated user, never an id supplied by the browser.
     */
    public function scopeForAthlete(Builder $query, User|int $athlete): Builder
    {
        return $query->where('athlete_id', $athlete instanceof User ? $athlete->id : $athlete);
    }

    /**
     * Certificates live on the private disk, so they are served through an
     * authorized route instead of a public URL.
     */
    public function hasCertificate(): bool
    {
        return filled($this->certificate_path)
            && Storage::disk('private')->exists($this->certificate_path);
    }

    public function certificateDiskName(): string
    {
        return 'achievement-'.$this->id.'.'.pathinfo((string) $this->certificate_path, PATHINFO_EXTENSION);
    }

    public function isMedal(): bool
    {
        return in_array($this->achievement_type, self::MEDAL_TYPES, true);
    }

    /**
     * The competition label, preferring the linked event so a linked event and
     * a typed competition never have to be kept in sync twice.
     */
    public function competitionLabel(): string
    {
        return $this->competition ?: ($this->event?->title ?? '');
    }

    public function sportLabel(): string
    {
        return $this->sport?->name ?? 'Unassigned sport';
    }

    /**
     * A short grouping used for the badge on cards and list rows.
     */
    public function category(): string
    {
        if ($this->isMedal()) {
            return 'Medal';
        }

        return match (true) {
            in_array($this->achievement_type, ['Champion', 'Runner-up'], true) => 'Title',
            in_array($this->achievement_type, ['MVP', 'Best Player', 'Best Athlete', 'Award'], true) => 'Award',
            $this->achievement_type === 'Qualification' => 'Qualification',
            $this->achievement_type === 'Participation' => 'Participation',
            default => 'Other',
        };
    }

    public function dateAchievedLabel(): string
    {
        return $this->date_achieved?->format('M j, Y') ?? 'Not recorded';
    }

    /**
     * Year of the achievement, used to group records on the athlete timeline.
     */
    public function year(): ?int
    {
        return $this->date_achieved instanceof Carbon ? $this->date_achieved->year : null;
    }
}