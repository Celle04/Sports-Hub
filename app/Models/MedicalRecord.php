<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $fillable = [
        'athlete_id',
        'user_id',
        'examination_date',
        'medical_status',
        'next_checkup_date',
        'findings',
        'restrictions',
        'notes',
        'medical_certificate',
        'last_checkup',
        'clearance',
    ];

    protected function casts(): array
    {
        return [
            'examination_date' => 'date',
            'next_checkup_date' => 'date',
            'last_checkup' => 'date',
        ];
    }

    public function athlete()
    {
        return $this->belongsTo(User::class, $this->athlete_id ? 'athlete_id' : 'user_id');
    }
}
