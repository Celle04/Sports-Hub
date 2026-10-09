<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Coach list filters shared by Coach Management and the Coach Report, so
     * both screens always return the same set of coaches for the same values.
     */
    public function scopeCoachFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($search !== '', fn (Builder $nested) => $nested->where(function (Builder $coachQuery) use ($search) {
                $coachQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('specialty', 'like', "%{$search}%");
            }))
            ->when(! empty($filters['sport_id']), fn (Builder $nested) => $nested->where('sport_id', (int) $filters['sport_id']))
            ->when(! empty($filters['coach_type']), fn (Builder $nested) => $nested->where('coach_type', (string) $filters['coach_type']))
            ->when(! empty($filters['status']), fn (Builder $nested) => $nested->where('status', (string) $filters['status']));
    }
}
