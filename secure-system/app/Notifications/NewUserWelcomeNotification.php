<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a newly created user with their credentials and role info.
 */
class NewUserWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $plainPassword
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to the SECURE System — Your Account is Ready')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your account has been created in the **SECURE System** by an Administrator.')
            ->line("**Role:** {$notifiable->role}")
            ->line("**Office Location:** {$notifiable->office_location}")
            ->line("**Email:** {$notifiable->email}")
            ->line("**Temporary Password:** `{$this->plainPassword}`")
            ->action('Log in Now', url('/login'))
            ->line('Please log in and change your password as soon as possible.')
            ->line('If you did not expect this account, contact your system administrator immediately.')
            ->salutation('The SECURE System Team');
    }
}
