<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class AttendanceNotification extends Notification
{
    public function __construct(
        public string $message,
        public ?string $url = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'message' => $this->message,
            'url' => $this->url,
        ]);
    }
}
