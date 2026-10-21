<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'team_name',
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
     * The certificate requests that were filed for this achievement.
     */
    public function certificateRequests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class);
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

    /**
     * The file name the stored certificate is downloaded under, for example
     * "certificate-achievement-2026-0001.pdf".
     */
    public function certificateFileName(): string
    {
        // Older records still hold a file that was attached before the
        // generated-PDF workflow existed, so the real extension is kept.
        $extension = strtolower(pathinfo((string) $this->certificate_path, PATHINFO_EXTENSION) ?: 'pdf');

        return 'certificate-achievement-'.$this->certificateYear().'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT).'.'.$extension;
    }

    /**
     * The file name of a freshly generated PDF for this achievement.
     */
    public function certificatePdfName(): string
    {
        return 'certificate-achievement-'.$this->certificateYear().'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT).'.pdf';
    }

    /**
     * The unique reference printed on the certificate, for example
     * "SNNHS-ACH-2026-0001".
     */
    public function certificateReference(): string
    {
        return config('certificate.reference_prefix', 'SNNHS-ACH')
            .'-'.$this->certificateYear()
            .'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * The year the achievement was earned, which keeps the reference and the
     * file name stable for the life of the certificate.
     */
    private function certificateYear(): int
    {
        return $this->date_achieved?->year ?? now()->year;
    }

    /**
     * A team achievement is won by a team, so it has no single athlete
     * account. The team name is what the certificate is issued to.
     */
    public function isTeam(): bool
    {
        return filled($this->team_name);
    }

    /**
     * The name printed on the certificate: the team for a team achievement,
     * the athlete otherwise.
     */
    public function recipientName(): string
    {
        if ($this->isTeam()) {
            return (string) $this->team_name;
        }

        return $this->athlete?->name ?? 'Athlete';
    }

    /**
     * How the recipient is described on the certificate and in the lists.
     */
    public function recipientLabel(): string
    {
        return $this->isTeam() ? 'Team' : 'Athlete';
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