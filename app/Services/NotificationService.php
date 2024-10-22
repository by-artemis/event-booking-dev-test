<?php

namespace App\Services;

use Log;
use Notification;
use Carbon\Carbon;
use App\Models\Booking;
use Spatie\CalendarLinks\Link;
use App\Notifications\EventReminderNotification;
use App\Notifications\BookingConfirmedNotification;

class NotificationService
{
    /**
     * Sends a confirmation email to the attendee of a booking event.
     * @param \App\Models\Booking $booking
     * @return void
     */
    public function sendConfirmationEmail(Booking $booking)
    {
        try {
            $event = $booking->event;

            $bookingData = [
                'event_name' => $event->name,
                'booking_date' => $booking->booking_date,
                'booking_time' => $booking->booking_time,
                'duration' => $event->duration,
                'attendee_email' => $booking->attendee_email,
                'attendee_name' => $booking->attendee_name,
                'ics_attachment' => $this->generateCalendarICS($event, $booking),
            ];

            // Use the Notification facade to send the booking confirmation
            Notification::send(
                $booking,
                new BookingConfirmedNotification($bookingData)
            );

            Log::info('[NotificationService] Booking confirmation email sent successfully!');
        } catch (\Exception $e) {
            Log::error('[NotificationService] Error in sendConfirmationEmail. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Sends an event reminder email to the attendee of a booking event.
     * @param \App\Models\Booking $booking
     * @return void
     */
    public function sendEventReminder(Booking $booking)
    {
        try {
            $event = $booking->event;

            $dateTime = Carbon::parse("$booking->booking_date $booking->booking_time");
            $reminderTime = $dateTime->subMinutes(60); // 1 hour before the scheduled booking

            $bookingData = [
                'event_name' => $event->name,
                'booking_date' => $booking->booking_date,
                'booking_time' => $booking->booking_time,
                'duration' => $event->duration,
                'attendee_email' => $booking->attendee_email,
                'attendee_name' => $booking->attendee_name,
                'reminder_time' => $reminderTime,
            ];

            // Use the Notification facade to send the reminder an hour before schedule
            Notification::send(
                $booking,
                (new EventReminderNotification($bookingData))->delay($reminderTime)
            );

            Log::info('[NotificationService] Event reminder email is now being queued!');
        } catch (\Exception $e) {
            Log::error('[NotificationService] Error in sendEventReminder. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Generates a Google Calendar ICS attachment for a booking event
     * @param mixed $event
     * @param mixed $booking
     * @return string|void
     */
    private function generateCalendarICS($event, $booking)
    {
        try {
            $startTime = Carbon::parse("$booking->booking_date $booking->booking_time");
            $endTime = $startTime->copy()->addMinutes($event->duration);
    
            $calendarLink = Link::create($booking->event->name, $startTime, $endTime)
                ->description($booking->event->description);
    
            Log::info('[NotificationService] Calendar ICS attachment generated successfully!');

            return $calendarLink->google();
        } catch (\Exception $e) {
            Log::error('[NotificationService] Error in generateCalendarICS. E: ', [
                $e->getMessage()
            ]);
        }
    }
}