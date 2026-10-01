<?php

namespace App\Models;

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
}
