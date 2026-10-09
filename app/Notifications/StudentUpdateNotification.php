<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class StudentUpdateNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $meta  Extra payload merged into the stored
     *                                   notification data, used to reference the
     *                                   source record (e.g. announcement_id) and
     *                                   to keep the feed free of duplicates.
     */
    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public array $meta = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage(array_filter([
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            ...$this->meta,
        ], fn (mixed $value) => $value !== null));
    }
}