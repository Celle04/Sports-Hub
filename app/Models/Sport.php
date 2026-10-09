<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    protected $fillable = ['name', 'classification', 'description', 'status'];

    public function athletes() { return $this->hasMany(User::class); }
    public function attendanceSessions() { return $this->hasMany(AttendanceSession::class); }
    public function coaches() { return $this->hasMany(Coach::class); }
    public function applications() { return $this->hasMany(Application::class); }
    public function events() { return $this->hasMany(Event::class); }
    public function announcements() { return $this->hasMany(Announcement::class); }
    public function achievements() { return $this->hasMany(Achievement::class); }
}
