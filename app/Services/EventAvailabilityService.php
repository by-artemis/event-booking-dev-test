<?php

namespace App\Services;

use Log;
use Carbon\Carbon;

class EventAvailabilityService
{
    protected GoogleCalendarService $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Checks the availability of a specified time slot on Google Calendar.
     * @param mixed $timezone
     * @param mixed $date
     * @param mixed $time
     * @param mixed $duration
     * @return array
     */
    public function getTimeSlotAvailability($timezone, $date, $time, $duration)
    {
        try {
            $this->calendarService->getClient();

            $startTime = Carbon::parse("$date $time");
            $endTime = $startTime->copy()->addMinutes($duration);

            $hasConflictingEvents = $this->hasConflictingEvents($startTime, $endTime, $timezone);

            if ($hasConflictingEvents) {
                $nextSlots = $this->getNextAvailableSlots($timezone, $startTime, $duration);

                return [
                    'isAvailable' => false,
                    'nextSlots' => $nextSlots,
                    'isMovedToNextDay' => !$nextSlots,
                ];
            }

            return [
                'isAvailable' => count($hasConflictingEvents) === 0
            ];
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in getTimeSlotAvailability. E: ', [
                $e->getMessage()
            ]);
            return ['isAvailable' => false];
        }
    }

    /**
     * Identifies the next available time slots avoiding collision and conflicts, respecting booking time restrictions.
     * @param mixed $timezone
     * @param mixed $dateTime
     * @param mixed $duration
     * @param mixed $calendarId
     * @return array|bool
     */
    private function getNextAvailableSlots($timezone, $dateTime, $duration, $calendarId = 'primary')
    {
        $busyTimeSlots = $this->calendarService->getBusyTimeSlots(
            $timezone,
            $dateTime,
            $calendarId
        );

        $dateTimeStart = $dateTime->copy()->timezone($timezone);
        $freeSlots = [];
        $onlyUntil = config('booking.end_time');
        $restrictedEndTime = Carbon::parse($dateTimeStart->format('Y-m-d') . $onlyUntil);

        // If time slot is beyond set restricted booking end time, extend to the next day
        if ($dateTimeStart->between($restrictedEndTime, $restrictedEndTime->copy()->addMinutes(59))) {
            return false;
        }

        foreach ($busyTimeSlots as $slot) {
            $busyStart = Carbon::parse($slot['start']);
            $busyEnd = Carbon::parse($slot['end']);

            // Skip if the booked slot is before the given datetime
            if ($busyEnd->lt($dateTimeStart)) {
                continue;
            }

            // Check if the given datetime falls within the booked slot
            if ($dateTimeStart->between($busyStart, $busyEnd)) {
                $dateTimeStart = $busyEnd;
            }

            // Adjust the next free slot start time if it overlaps with the current booked slot
            while ($freeSlots && Carbon::parse($freeSlots[0]['start'])->lt($busyEnd)) {
                array_shift($freeSlots);
            }
        }

        // Generate free slots, ensuring they start on the hour or half-hour
        for ($i = 0; $i < 3; $i++) {
            // Round up the start time to the nearest hour or half-hour
            $start = $dateTimeStart->copy()->roundMinutes(30);

            // Ensure the start time is after the current busy slot
            if ($start->lt($dateTimeStart)) {
                $start->addMinutes(30);
            }

            // Calculate the end time
            $end = $start->copy()->addMinutes($duration);

            // Check if the end time overlaps with any booked slots
            foreach ($busyTimeSlots as $slot) {
                if ($end->between(Carbon::parse($slot['start']), Carbon::parse($slot['end']))) {
                    // If overlapping, adjust the start time to the end of the overlapping slot and continue the loop
                    $start = Carbon::parse($slot['end'])->roundMinutes(30);
                    $end = $start->copy()->addMinutes($duration);
                    continue;
                }
            }

            $freeSlots[] = $start->format('m-d-Y H:i') . ' to ' . $end->format('m-d-Y H:i');
            $dateTimeStart = $end;
        }

        return $freeSlots;
    }

    /**
     * Initially checks for conflicts between a given time slot.
     * @param mixed $events
     * @param mixed $startTime
     * @param mixed $endTime
     * @return mixed
     */
    private function hasConflictingEvents($startTime, $endTime, $timezone = 'UTC')
    {
        $events = $this->calendarService->getCalendarEvents(
            $startTime,
            $endTime,
            $timezone
        );

        return array_filter($events, function ($event) use ($startTime, $endTime) {
            $existingEventStart = Carbon::parse($event['start']);
            $existingEventEnd = Carbon::parse($event['end']);

            return $existingEventStart->lt($endTime) && $existingEventEnd->gt($startTime);
        });
    }
}

