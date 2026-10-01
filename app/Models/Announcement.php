<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'sport_id', 'published_at', 'status'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }
}
