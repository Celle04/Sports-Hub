<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['title', 'description', 'venue', 'starts_at', 'ends_at', 'status', 'sport_id'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}