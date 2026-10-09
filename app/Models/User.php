<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'username', 'role', 'status', 'sport_id', 'student_id', 'phone', 'grade_level', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'profile_photo_path', 'password'];
    protected $hidden = ['password', 'remember_token'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Attendance sessions this athlete is eligible for, based on assigned sport.
     */
    public function attendanceSessions()
    {
        return AttendanceSession::query()->visibleToAthlete($this);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class, 'athlete_id');
    }

    /**
     * Injuries and medical incidents recorded for this athlete.
     */
    public function medicalIncidents()
    {
        return $this->hasMany(MedicalIncident::class, 'athlete_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'athlete_id');
    }

    /**
     * Accomplishments recorded for this athlete.
     */
    public function achievements()
    {
        return $this->hasMany(Achievement::class, 'athlete_id');
    }

    /**
     * Sports this athlete belongs to: the sport assigned on the account plus
     * every sport coming from an approved application.
     *
     * @return array<int, int>
     */
    public function sportIds(): array
    {
        return collect([$this->sport_id])
            ->merge($this->applications()
                ->where('status', 'Approved')
                ->whereNotNull('sport_id')
                ->pluck('sport_id'))
            ->filter()
            ->unique()
            ->map(fn ($sportId) => (int) $sportId)
            ->values()
            ->all();
    }

    /**
     * Coaches assigned to the sports this athlete belongs to.
     */
    public function coaches()
    {
        return Coach::query()
            ->with('sport')
            ->whereIn('sport_id', $this->sportIds())
            ->where('status', 'Active')
            ->orderBy('name');
    }

    /**
     * The athlete's own sports, each carrying only its active coaches so the
     * coach module can flag the sports that have no coach yet.
     */
    public function sportsWithCoaches()
    {
        return Sport::query()
            ->whereIn('id', $this->sportIds())
            ->with(['coaches' => fn ($query) => $query->where('status', 'Active')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Athlete list filters shared by Athlete Management and the Athlete
     * Report, so both screens always return the same set of athletes for the
     * same search and filter values.
     */
    public function scopeAthleteFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($search !== '', fn (Builder $nested) => $nested->where(function (Builder $athleteQuery) use ($search) {
                $athleteQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(! empty($filters['sport_id']), fn (Builder $nested) => $nested->where('sport_id', (int) $filters['sport_id']))
            ->when(! empty($filters['grade']), fn (Builder $nested) => $nested->whereHas('applications', fn (Builder $applications) => $applications->where('grade', (string) $filters['grade'])))
            ->when(! empty($filters['gender']), fn (Builder $nested) => $nested->whereHas('applications', fn (Builder $applications) => $applications->where('gender', (string) $filters['gender'])))
            ->when(! empty($filters['status']), fn (Builder $nested) => $nested->where('status', (string) $filters['status']))
            ->when(! empty($filters['eligibility']), fn (Builder $nested) => $this->applyEligibilityFilter($nested, (string) $filters['eligibility']));
    }

    /**
     * The eligibility rules shown on the Athlete Management list.
     */
    private function applyEligibilityFilter(Builder $query, string $eligibility): Builder
    {
        return match ($eligibility) {
            'Eligible' => $query->whereHas('medicalRecords', fn (Builder $medical) => $medical->where('medical_status', 'Cleared')),
            'Not Eligible' => $query->where(function (Builder $notEligible) {
                $notEligible->whereHas('medicalRecords', fn (Builder $medical) => $medical->whereIn('medical_status', ['Not Cleared', 'Restricted']))
                    ->orWhereHas('applications', fn (Builder $applications) => $applications->where('status', 'Rejected'));
            }),
            default => $query->where(function (Builder $pending) {
                $pending->whereDoesntHave('medicalRecords', fn (Builder $medical) => $medical->where('medical_status', 'Cleared'))
                    ->whereDoesntHave('applications', fn (Builder $applications) => $applications->where('status', 'Rejected'));
            }),
        };
    }

    /**
     * Public URL of the uploaded profile photo, when the athlete has one.
     */
    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    /**
     * The athlete's current grade level.
     *
     * Prefers the value the athlete keeps on their profile and falls back to
     * the grade captured on their most recent application, so the field is
     * never blank for an athlete who applied before it became editable.
     */
    public function gradeLevel(): ?string
    {
        if (filled($this->grade_level)) {
            return $this->grade_level;
        }

        return $this->applications->sortByDesc('created_at')->first()?->grade;
    }

    /**
     * Whether a usable emergency contact has been recorded. A name and a
     * number are what staff actually need to place a call.
     */
    public function hasEmergencyContact(): bool
    {
        return filled($this->emergency_contact_name) && filled($this->emergency_contact_phone);
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    /**
     * Up to two initials used by the default profile avatar.
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $words = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($words === []) {
                return strtoupper(substr((string) $this->email, 0, 2));
            }

            $initials = mb_substr($words[0], 0, 1);
            if (count($words) > 1) {
                $initials .= mb_substr(end($words), 0, 1);
            }

            return mb_strtoupper($initials);
        });
    }
}
