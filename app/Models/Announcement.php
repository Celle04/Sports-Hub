<?php

namespace App\Models;

use App\Support\RelativeTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'sport_id', 'published_at', 'status'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Announcements that are officially published and whose publish date has
     * arrived. Drafts, archived posts and future-dated posts are excluded.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'Published')
            ->where(fn (Builder $published) => $published->whereNull('published_at')->orWhereDate('published_at', '<=', today()));
    }

    /**
     * Published announcements this athlete is allowed to read: everything
     * aimed at all athletes plus anything aimed at one of their own sports.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisibleTo(Builder $query, User $athlete): Builder
    {
        $sportIds = $athlete->sportIds();

        return $query->published()
            ->where(fn (Builder $scoped) => $sportIds === []
                ? $scoped->whereNull('sport_id')
                : $scoped->whereNull('sport_id')->orWhereIn('sport_id', $sportIds));
    }

    /**
     * Active student athletes that should be notified about this announcement.
     * An announcement without a sport reaches every active athlete; a sport
     * specific announcement only reaches athletes assigned to that sport either
     * on their account or through an approved application.
     *
     * @return Builder<User>
     */
    public function audience()
    {
        return User::query()
            ->where('role', 'Student')
            ->where('status', 'Active')
            ->when($this->sport_id, fn (Builder $query) => $query->where(fn (Builder $scoped) => $scoped
                ->where('sport_id', $this->sport_id)
                ->orWhereHas('applications', fn (Builder $applications) => $applications
                    ->where('status', 'Approved')
                    ->where('sport_id', $this->sport_id))));
    }

    /**
     * Whether the announcement is currently live for athletes.
     */
    public function isVisible(): bool
    {
        return $this->status === 'Published'
            && (! $this->published_at || $this->published_at->lessThanOrEqualTo(today()));
    }

    /**
     * The moment to show as the announcement's posting time.
     *
     * This is `created_at` on purpose:
     *
     *  - `updated_at` moves every time an admin re-saves the post, so using it
     *    would make an old announcement look freshly posted.
     *  - `published_at` is a DATE column used for scheduling a future release.
     *    It carries no time of day, so it resolves to midnight and measures
     *    the gap from the start of the day rather than from the post itself.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function postedAt()
    {
        return $this->created_at;
    }

    /**
     * Posting time as relative text, e.g. "Just now", "5 minutes ago".
     */
    public function postedForHumans(): string
    {
        return RelativeTime::of($this->postedAt());
    }

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }
}