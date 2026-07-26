<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    /**
     * Create a notification instance.
     */
    public function __construct(
        #[\SensitiveParameter] public string $token
    ) {}

    /**
     * Get the notification's channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expire = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60
        );

        return (new MailMessage)
            ->subject('Reset your VERO Property Management password')
            ->view('emails.reset-password', [
                'url' => $url,
                'expire' => $expire,
                'userName' => $notifiable->name ?? null,
                'logoUrl' => asset('images/vero_pm_logo.png'),
                'appName' => config('app.name', 'Property MG'),
            ]);
    }
}
