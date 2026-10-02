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

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }
}
