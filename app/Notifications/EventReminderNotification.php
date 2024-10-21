<?php

namespace App\Notifications;

use Log;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Mail\EventReminderMail;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $booking;

    /**
     * Create a new notification instance.
     */
    public function __construct($booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): Mailable
    {
        Log::info('[EventReminderNotification] Event reminder email sent successfully!');

        return (new EventReminderMail($this->booking))
            ->to($notifiable->attendee_email);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
