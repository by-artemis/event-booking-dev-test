<?php

namespace App\Services;

use Log;
use Carbon\Carbon;
use App\Models\Booking;

class BookingService
{
    protected $service;

    public function __construct(Booking $booking)
    {
        $this->service = $booking;
    }

    public function createBooking($bookingData)
    {
        try {
            Log::info('[BookingService] Booking inserted to database successfully!');

            return $this->service->create($bookingData);
        } catch (\Exception $e) {
            Log::error('[BookingService] Error in createBooking. E: ', [
                $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function generateTimeSlots($date, $timezone, $eventId)
    {
        // Use a consistent timezone for now()
        $now = Carbon::now($timezone);

        // Start timeslots at 8:00AM and end until 5:00PM
        $startOfDay = Carbon::parse($date)->setTime(8, 0)->timezone($timezone);
        $endOfDay = Carbon::parse($date)->setTime(17, 59)->timezone($timezone);

        $interval = 30; // Fix interval of 30 minutes

        // Get bookings for the selected date and event
        $bookedSlots = Booking::where('event_id', $eventId)
            ->whereDate('booking_date', $date)
            ->get()
            ->pluck('booking_time')
            ->toArray();

        $timeSlots = [];

        // If the selected date is today, 
        if ($date === $now->format('Y-m-d')) {
            // Start 1 hour after of the current time if later than 8:00AM
            if ($now->startOfHour()->hour > 8) {
                $startOfDay = $now->startOfHour()->addHour();
            }
        }

        while ($startOfDay < $endOfDay) {
            $slotTime = $startOfDay->format('H:i');

            // Check if the current time slot is already booked
            $isAvailable = !in_array($slotTime, $bookedSlots);

            $timeSlots[] = [
                'time' => $slotTime,
                'is_available' => $isAvailable,
            ];

            $startOfDay->addMinutes($interval);

            if ($startOfDay->hour === 17 && $startOfDay->minute === 30) {
                $startOfDay->subMinutes($interval);
                break;
            }
        }

        return $timeSlots;
    }
}
