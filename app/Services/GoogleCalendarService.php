<?php

namespace App\Services;

use Log;
use Session;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime as GoogleEventDateTime;

class GoogleCalendarService
{
    protected $client;
    protected $googleCalendar;

    public function __construct(GoogleClient $client = null)
    {
        // $this->client = new GoogleClient();
        $this->client = $client ?? new GoogleClient();

        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->setPrompt(config('services.google.prompt'));
        $this->client->setApprovalPrompt(config('services.google.approval_prompt'));
        $this->client->setAccessType(config('services.google.access_type'));
        $this->client->addScope(config('services.google.scopes'));

        $this->googleCalendar = new GoogleCalendar($this->client);
    }

    /**
     * Authenticates with Google Calendar using an authorization code.
     * @param mixed $code
     * @return void
     */
    public function authenticate($code)
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

            Session::put('google_access_token', $accessToken['access_token']);

            $this->client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);
            
            Log::info('[GoogleCalendarService] Google Calendar authenticated successfully!');
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Error in authenticate. E: ', [
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
            if (Session::get('google_access_token')) {
                $this->client->setAccessToken(Session::get('google_access_token'));
            }

            Log::info('[GoogleCalendarService] Google Calendar client stored to session successfully!');

            return $this->client;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Error in getClient. E: ', [
                $e->getMessage()
            ]);
        }
    }

    public function getGoogleCalendar()
    {
        try {
            if (!$this->client->isAccessTokenExpired()) {
                $this->googleCalendar = new GoogleCalendar($this->client);
            }

            Log::info('[GoogleCalendarService] Google Calendar client stored to session successfully!');

            return $this->googleCalendar;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Error in getClient. E: ', [
                $e->getMessage()
            ]);
            return null;
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

                Log::info('[GoogleCalendarService] Google Calendar access revoked successfully!');
            } else {
                Log::info('[GoogleCalendarService] Google Calendar access has already been revoked.');
            }
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Error in revokeAccess. E: ', [
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
            $this->getClient();

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

            Log::info('[GoogleCalendarService] Event inserted to Google Calendar successfully!');

            // Insert event to Google Calendar
            return $this->googleCalendar->events->insert($calendarId, $event);
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Error in createEventToGoogleCalendar. E: ', [
                $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Retrieves a list of events from the user's primary Google Calendar.
     * @param mixed $startTime
     * @param mixed $endTime
     * @param mixed $timezone
     * @param mixed $isStartOfDay
     * @return array{end: string, start: string, timeZone: string}
     */
    public function getCalendarEvents($startTime = '', $endTime = '', $timezone = 'UTC', $isStartOfDay = true)
    {
        try {
            $optParams = [
                'timeZone' => $timezone,
                'timeMin' => Carbon::parse($startTime)->startOfDay()->toIso8601String(), 
                'timeMax' => Carbon::parse($endTime)->endOfDay()->toIso8601String(),
                'singleEvents' => true,
            ];

            if (!$isStartOfDay) {
                $optParams = [
                    'timeMin' => $startTime->toIso8601String(), 
                    'timeMax' => $endTime->toIso8601String()
                ];
            }

            $calendarId = 'primary';

            $events = $this->googleCalendar->events->listEvents($calendarId, $optParams);

            Log::info('[GoogleCalendarService] Events from Google Calendar retrieved successfully!');

            return array_map(function ($event) use ($timezone) {
                return [
                    'start' => Carbon::parse($event->start->dateTime)->format('Y-m-d H:i'),
                    'end' => Carbon::parse($event->end->dateTime)->format('Y-m-d H:i'),
                    'timeZone' => $timezone,
                ];
            }, $events->getItems());
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Error in getCalendarEvents. E: ', [
                $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Retrieves a list of busy time slots within a specified timezone, time slot, and calendar.
     * @param mixed $timezone
     * @param mixed $dateTime
     * @param mixed $calendarId
     * @return Carbon[][]
     */
    public function getBusyTimeSlots($timezone, $dateTime, $calendarId = 'primary')
    {
        $freeBusyRequest = new FreeBusyRequest();
        $freeBusyRequest->setTimeMin(Carbon::parse($dateTime)->timezone($timezone)->startOfDay()->toIso8601String());
        $freeBusyRequest->setTimeMax(Carbon::parse($dateTime)->timezone($timezone)->endOfDay()->toIso8601String());
        $freeBusyRequest->setTimeZone($timezone);
        $freeBusyRequest->setItems([['id' => $calendarId]]);

        $freeBusyResponse = $this->googleCalendar->freebusy->query($freeBusyRequest);
        $calendars = $freeBusyResponse->getCalendars();

        $busySlots = $calendars[$calendarId]->busy;

        return array_map(function ($slot) {
            return [
                'start' => Carbon::parse($slot->start),
                'end' => Carbon::parse($slot->end),
            ];
        }, $busySlots);
    }
}
