<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coach extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'specialty', 'coach_type', 'sport_id', 'status'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
