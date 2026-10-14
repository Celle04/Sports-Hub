<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BrokerNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * SportsHub-branded password reset mail.
 *
 * Extends the notification Laravel's password broker already sends, so the
 * token, the email parameters and the APP_URL-based reset link are all
 * generated exactly the way the framework intends. Only the copy is ours.
 */
class ResetPasswordNotification extends BrokerNotification
{
    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $expireMinutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60
        );

        return (new MailMessage)
            ->subject('Reset Your SportsHub Password')
            ->greeting('Hello,')
            ->line('We received a request to reset your password for the SNNHS Sports Activity Hub.')
            ->line('Use the button below to create a new password.')
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line("This link will expire after {$expireMinutes} minutes. If you did not request a password reset, you can ignore this email.")
            ->salutation('SNNHS Sports Activity Hub');
    }
}
