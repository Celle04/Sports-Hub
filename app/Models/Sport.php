<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    protected $fillable = ['name', 'classification', 'gender', 'description', 'status', 'logo_path'];

    public function athletes() { return $this->hasMany(User::class); }
    public function attendanceSessions() { return $this->hasMany(AttendanceSession::class); }
    public function coaches() { return $this->hasMany(Coach::class); }
    public function applications() { return $this->hasMany(Application::class); }
    public function events() { return $this->hasMany(Event::class); }
    public function achievements() { return $this->hasMany(Achievement::class); }

    /**
     * The coach currently assigned to this sport.
     *
     * A sport may hold several coach records, so the most recently assigned
     * one is the coach that represents the program. Sports without a coach
     * simply return null.
     */
    public function assignedCoach(): ?Coach
    {
        if ($this->relationLoaded('coaches')) {
            return $this->coaches->sortByDesc('id')->first();
        }

        return $this->coaches()->orderByDesc('id')->first();
    }
}
