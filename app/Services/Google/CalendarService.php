<?php

namespace App\Services\Google;

use Log;
use Carbon\Carbon;
use Google\Client as GoogleClient;
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

    public function authenticate($code)
    {
        try {
            $this->client->fetchAccessTokenWithAuthCode($code);

            session(['google_access_token' => $this->client->getAccessToken()]);

            $this->client->fetchAccessTokenWithRefreshToken(session('google_access_token'));

            // $accessToken = $this->client->getAccessToken()['access_token'];
            // $refreshToken = $this->client->getRefreshToken();

            // GoogleToken::create([
            //     'access_token' => $accessToken,
            //     'refresh_token' => $refreshToken,
            //     'user_id' => 1,
            // ]);

            Log::info('[CalendarService] Google Calendar authenticated successfully!');
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in authenticate. E: ', [
                $e->getMessage()
            ]);

            throw $e;
        }
    }

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

            throw $e;
        }
    }

    public function revokeAccess()
    {
        try {
            $isRevoked = $this->getClient()->revokeToken($this->getClient()->getRefreshToken() ?? null);

            if ($isRevoked) {
                session()->remove('google_access_token');

                // TODO: delete from db

                Log::info('[CalendarService] Google Calendar access revoked successfully!');
            } else {
                Log::info('[CalendarService] Google Calendar access has already been revoked.');
            }
        } catch (\Exception $e) {
            Log::error('[CalendarService] Error in revokeAccess. E: ', [
                $e->getMessage()
            ]);

            throw $e;
        }
    }

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

            throw $e;
        }
    }

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

            throw $e;
        }
    }

    private function getNextAvailableSlots($timezone, $events, $dateTime, $duration, $calendarId = 'primary')
    {
        $availableTimeSlots = [];

        $startOfDateTime = $events->getItems()[0]->start->dateTime;
        $endOfDayDateTime = Carbon::parse($events->getItems()[0]->end->dateTime)->endOfDay()->toIso8601String();
        $bookedTimeSlots = $this->getBookedTimeSlotsOfTheDay($startOfDateTime, $endOfDayDateTime, $calendarId);

        $dateTimeStart = $dateTime->copy()->timezone($timezone);

        $i = 0;
        $max = 3;

        $onlyUntil = config('booking.end_time');

        // Find the next 3 available slots
        while ($i < $max) {
            // Check if the current slot is booked
            $isBooked = false;

            foreach ($bookedTimeSlots as $bookedSlot) {
                $bookedSlotStart = $bookedSlot['startTime']->copy()->timezone($timezone);
                $bookedSlotEnd = $bookedSlot['endTime']->copy()->timezone($timezone);

                $restrictedEndTime = Carbon::parse($dateTimeStart->format('Y-m-d') . $onlyUntil);

                // If time slot is beyond set restricted booking end time, extend to the next day
                if ($dateTimeStart->between($restrictedEndTime, $restrictedEndTime->copy()->addMinutes(59))) {
                    $dateTimeStart = $dateTimeStart->copy()->addDay()->setTime(8, 0);
                    return false;
                }

                if ($dateTimeStart->between($bookedSlotStart, $bookedSlotEnd)) {
                    $isBooked = true;
                    break;
                }
            }

            // If not booked, add the slot to the available slots
            if (!$isBooked) {
                $endTime = $dateTimeStart->copy()->addMinutes($duration)->timezone($timezone);
                $availableTimeSlots[] = $dateTimeStart->copy()->format('Y-m-d H:i') . ' to ' . $endTime->format('Y-m-d H:i');
                $i++;
            }

            // Move to the next slot
            $dateTimeStart = $dateTimeStart->addMinutes($duration);
        }

        return $availableTimeSlots;
    }

    private function getBookedTimeSlotsOfTheDay($startOfDateTime, $endOfDayDateTime = '', $calendarId)
    {
        // $endOfDayDateTime = Carbon::parse($events->getItems()[0]->end->dateTime)->endOfDay()->toIso8601String();
        // $startTime = $events->getItems()[0]->start->dateTime;
        $eventsOfTheDay = $this->service->events->listEvents($calendarId, [
            'timeMin' => $startOfDateTime,
            'timeMax' => $endOfDayDateTime,
            'singleEvents' => true, // Only check for single events
            'orderBy' => 'startTime',
        ]);

        return array_map(function ($event) {
            return [
                'eventId' => $event->id,
                'startTime' => Carbon::parse($event->start->dateTime),
                'endTime' => Carbon::parse($event->end->dateTime),
            ];
        }, $eventsOfTheDay->getItems());
    }

    private function getConflictingEvents($events, $startTime, $endTime)
    {
        return array_filter($events->getItems(), function ($event) use ($startTime, $endTime) {
            $existingEventStart = Carbon::parse($event->getStart()->getDateTime());
            $existingEventEnd = Carbon::parse($event->getEnd()->getDateTime());

            return $existingEventStart->lte($endTime) && $existingEventEnd->gte($startTime);
        });
    }
}
