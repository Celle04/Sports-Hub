<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A news item for the public landing page.
 *
 * Announcements are published by the portal itself: a scheduled event posts
 * one so the school website shows the new activity straight away.
 */
class Announcement extends Model
{
    public const STATUS_PUBLISHED = 'Published';

    public const STATUS_DRAFT = 'Draft';

    protected $fillable = [
        'title',
        'body',
        'published_at',
        'status',
        'event_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
        ];
    }

    /**
     * The event the announcement talks about, when there is one.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The sport of the announced event, used for the tag on the card.
     */
    protected function sport(): Attribute
    {
        return Attribute::get(fn () => $this->event?->sport);
    }

    /**
     * Announcements that are ready to be shown on the landing page.
     *
     * An announcement of an event disappears from the page when that event is
     * called off or postponed.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $nested) {
                $nested->whereNull('published_at')
                    ->orWhereDate('published_at', '<=', today());
            })
            ->where(function (Builder $nested) {
                $nested->whereNull('event_id')
                    ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery
                        ->whereNotIn('status', ['Cancelled', 'Postponed']));
            });
    }

    /**
     * The announcement an event posts on the landing page.
     */
    public static function forEvent(Event $event): self
    {
        $details = $event->starts_at
            ? $event->starts_at->format('F j, Y \a\t g:i A')
            : 'a date to be confirmed';

        $body = $event->title.' is scheduled for '.$details.' at '.($event->venue ?: 'the school grounds').'.';

        if ($event->sport) {
            $body .= ' Athletes of the '.$event->sport->name.' program are invited to join.';
        }

        if ($event->team_name) {
            $body = $event->team_name.' — '.$body;
        }

        return self::create([
            'title' => 'New Event: '.$event->title,
            'body' => $body,
            'published_at' => today(),
            'status' => self::STATUS_PUBLISHED,
            'event_id' => $event->id,
        ]);
    }
}
