<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class CertificateRequestNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $meta  Extra payload merged into the stored
     *                                   notification data, used to reference the
     *                                   source record and to keep the feed free
     *                                   of duplicates via a `dedupe_key`.
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