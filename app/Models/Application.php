<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $fillable = ['name', 'student_id', 'grade', 'gender', 'email', 'sport', 'sport_id', 'medical_certificate_path', 'birth_certificate_path', 'parent_consent_path', 'medical_document_status', 'birth_document_status', 'consent_document_status', 'status', 'rejection_reason', 'review_notes', 'reviewed_by', 'reviewed_at', 'documents_requested', 'document_rejection_notes', 'review_history', 'athlete_id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'documents_requested' => 'array', 'document_rejection_notes' => 'array', 'review_history' => 'array'];
    }

    public function sportCategory()
    {
        return $this->belongsTo(Sport::class, 'sport_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function athlete()
    {
        return $this->belongsTo(User::class, 'athlete_id');
    }
}
