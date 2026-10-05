<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'username', 'role', 'status', 'sport_id', 'student_id', 'phone', 'profile_photo_path', 'password'];
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

    public function applications()
    {
        return $this->hasMany(Application::class, 'athlete_id');
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

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }
}
