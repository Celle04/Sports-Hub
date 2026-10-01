<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['title', 'description', 'venue', 'starts_at', 'ends_at', 'status', 'sport_id', 'event_type', 'coach_id', 'team_name', 'max_participants', 'notes'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}