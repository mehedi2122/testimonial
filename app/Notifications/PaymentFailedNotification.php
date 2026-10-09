<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Pro payment failed')
            ->line("We couldn't charge your card for the Pro plan.")
            ->line('Update your payment method to keep your Pro limits.')
            ->action('Open billing', route('billing.show'));
    }
}
