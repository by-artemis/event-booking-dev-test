<?php

namespace App\Services\Google;

use Log;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime as GoogleEventDateTime;

class CalendarService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new GoogleClient();

        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->setPrompt(config('services.google.prompt'));
        $this->client->setApprovalPrompt(config('services.google.approval_prompt'));
        $this->client->setAccessType(config('services.google.access_type'));

        $this->client->addScope(config('services.google.scopes'));

        $this->service = new GoogleCalendar($this->client);
    }

    /**
     * Authenticates with Google Calendar using an authorization code.
     * @param mixed $code
     * @return void
     */
    public function authenticate($code)
    {
        try {
            $this->client->fetchAccessTokenWithAuthCode($code);

            session(['google_access_token' => $this->client->getAccessToken()]);

            $this->client->fetchAccessTokenWithRefreshToken(session('google_access_token'));

            Log::info('[CalendarService] Google Calendar authenticated successfully!');
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in authenticate. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Creates a Google Client instance for interacting with Google Calendar API.
     * @return GoogleClient|void
     */
    public function getClient()
    {
        try {
            if (session('google_access_token')) {
                $this->client->setAccessToken(session('google_access_token'));
            }

            Log::info('[CalendarService] Google Calendar client stored to session successfully!');

            return $this->client;
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in getClient. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Revokes access to Google Calendar.
     * @return void
     */
    public function revokeAccess()
    {
        try {
            $isRevoked = $this->getClient()->revokeToken($this->getClient()->getRefreshToken() ?? null);

            if ($isRevoked) {
                session()->remove('google_access_token');

                Log::info('[CalendarService] Google Calendar access revoked successfully!');
            } else {
                Log::info('[CalendarService] Google Calendar access has already been revoked.');
            }
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in revokeAccess. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Retrieves a list of events from the user's primary Google Calendar.
     * @return mixed
     */
    public function getCalendarEvents($timezone = 'UTC')
    {
        try {
            $this->getClient();

            $calendarId = 'primary';

            $events = $this->service->events->listEvents($calendarId, [
                'timeZone' => $timezone
            ]);

            Log::info('[CalendarService] Events from Google Calendar retrieved successfully!');

            return array_map(function ($event) {
                return [
                    'start' => Carbon::parse($event->start->dateTime)->format('Y-m-d H:i'),
                    'end' => Carbon::parse($event->end->dateTime)->format('Y-m-d H:i'),
                ];
            }, $events->getItems());
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in getCalendarEvents. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Creates a new event on Google Calendar.
     * @param mixed $eventData
     * @param mixed $calendarId
     * @return mixed
     */
    public function createEventToGoogleCalendar($eventData, $calendarId = 'primary')
    {
        try {
            $event = new GoogleEvent($eventData);

            // Title & Description
            $event->setSummary($eventData->title);
            $event->setDescription($eventData->description);

            // Start DateTime
            $event->setStart(new GoogleEventDateTime([
                'dateTime' => $eventData->start,
                'timeZone' => 'UTC'
            ]));

            // End DateTime
            $event->setEnd(new GoogleEventDateTime([
                'dateTime' => $eventData->end,
                'timeZone' => 'UTC'
            ]));

            // Attendees
            $event->setAttendees([
                'displayName' => $eventData->attendee_name,
                'email' => $eventData->attendee_email,
            ]);

            // Insert event to Google Calendar
            $event = $this->service->events->insert($calendarId, $event);

            Log::info('[CalendarService] Event inserted to Google Calendar successfully!');

            return $event;
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in createEventToGoogleCalendar. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Checks the availability of a specified time slot on Google Calendar.
     * @param mixed $timezone
     * @param mixed $date
     * @param mixed $time
     * @param mixed $duration
     * @param mixed $calendarId
     * @return array|void
     */
    public function checkTimeSlotAvailability($timezone, $date, $time, $duration, $calendarId = 'primary')
    {
        try {
            $this->getClient();

            $startTime = Carbon::parse("$date $time");
            $endTime = $startTime->copy()->addMinutes($duration);

            $events = $this->service->events->listEvents($calendarId, [
                'timeMin' => $startTime->toIso8601String(),
                'timeMax' => $endTime->toIso8601String(),
                'singleEvents' => true, // Only check for single events
                'orderBy' => 'startTime',
            ]);

            // Check if the current time slot conflicts with existing events
            $conflictingEvents = $this->getConflictingEvents($events, $startTime, $endTime);

            if ($conflictingEvents) {
                $nextSlots = $this->getNextAvailableSlots($timezone, $events, $startTime, $duration);

                return [
                    'isAvailable' => false,
                    'nextSlots' => $nextSlots,
                    'isMovedTheNextDay' => !$nextSlots,
                ];
            }

            return [
                'isAvailable' => count($conflictingEvents) === 0
            ];
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in checkTimeSlotAvailability. E: ', [
                $e->getMessage()
            ]);
        }
    }

    /**
     * Identifies the next available time slots avoiding collision and conflicts 
     * with existing events and respecting booking time restrictions.
     * @param mixed $timezone
     * @param mixed $events
     * @param mixed $dateTime
     * @param mixed $duration
     * @param mixed $calendarId
     * @return array|bool
     */
    private function getNextAvailableSlots($timezone, $events, $dateTime, $duration, $calendarId = 'primary')
    {
        $busyTimeSlots = $this->getBusyTimeSlots($timezone, $dateTime, $calendarId);
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
     * Retrieves a list of busy time slots within a specified timezone, time slot, and calendar.
     * @param mixed $timezone
     * @param mixed $dateTime
     * @param mixed $calendarId
     * @return Carbon[][]
     */
    private function getBusyTimeSlots($timezone, $dateTime, $calendarId = 'primary')
    {
        $freeBusyRequest = new FreeBusyRequest();
        $freeBusyRequest->setTimeMin(Carbon::parse($dateTime)->timezone($timezone)->startOfDay()->toIso8601String());
        $freeBusyRequest->setTimeMax(Carbon::parse($dateTime)->timezone($timezone)->endOfDay()->toIso8601String());
        $freeBusyRequest->setTimeZone($timezone);
        $freeBusyRequest->setItems([['id' => $calendarId]]);

        $freeBusyResponse = $this->service->freebusy->query($freeBusyRequest);
        $calendars = $freeBusyResponse->getCalendars();

        $busySlots = $calendars[$calendarId]->busy;

        return array_map(function ($slot) {
            return [
                'start' => Carbon::parse($slot->start),
                'end' => Carbon::parse($slot->end),
            ];
        }, $busySlots);
    }

    /**
     * Initially checks for conflicts between a given time slot.
     * @param mixed $events
     * @param mixed $startTime
     * @param mixed $endTime
     * @return mixed
     */
    private function getConflictingEvents($events, $startTime, $endTime)
    {
        return array_filter($events->getItems(), function ($event) use ($startTime, $endTime) {
            $existingEventStart = Carbon::parse($event->getStart()->getDateTime());
            $existingEventEnd = Carbon::parse($event->getEnd()->getDateTime());

            return $existingEventStart->lte($endTime) && $existingEventEnd->gte($startTime);
        });
    }
}
