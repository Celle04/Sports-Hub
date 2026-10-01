<?php

namespace App\Models;

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
        'status',
        'created_by',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
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
}
