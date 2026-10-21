<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateRequest extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_ISSUED = 'Issued';

    public const STATUS_REJECTED = 'Rejected';

    /**
     * Every status apart from these still needs an admin's attention.
     *
     * @var array<int, string>
     */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED];

    protected $fillable = [
        'achievement_id',
        'student_id',
        'status',
        'remarks',
        'handled_by',
        'handled_at',
        'certificate_path',
        'certificate_generated_at',
        'certificate_reference',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'certificate_generated_at' => 'datetime',
        ];
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeForStudent(Builder $query, User|int $student): Builder
    {
        return $query->where('student_id', $student instanceof User ? $student->id : $student);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * The PDF has been generated and stored for this request.
     */
    public function isGenerated(): bool
    {
        return filled($this->certificate_path) && $this->certificate_generated_at !== null;
    }

    /**
     * The certificate has been marked ready for release, so the student may
     * download it.
     */
    public function isReleased(): bool
    {
        return $this->status === self::STATUS_ISSUED && $this->isGenerated();
    }

    /**
     * The reference printed on the certificate, for example
     * "SNNHS-ACH-2026-0001".
     */
    public function certificateReference(): string
    {
        if (filled($this->certificate_reference)) {
            return (string) $this->certificate_reference;
        }

        $achievement = $this->achievement;
        $year = $achievement?->date_achieved?->year ?? now()->year;

        return config('certificate.reference_prefix', 'SNNHS-ACH').'-'.$year.'-'.str_pad((string) ($achievement?->id ?? 0), 4, '0', STR_PAD_LEFT);
    }

    public function issuedCertificateName(): string
    {
        return 'certificate-request-'.$this->id.'.pdf';
    }
}