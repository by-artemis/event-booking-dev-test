<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Carbon\Carbon;
use App\Models\Event;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Services\GoogleCalendarService;
use App\Services\EventAvailabilityService;
use App\Http\Requests\CreateBookingRequest;

class BookingController extends Controller
{
    protected BookingService $bookingService;
    protected GoogleCalendarService $googleCalendarService;
    protected EventAvailabilityService $eventAvailabilityService;
    protected NotificationService $notificationService;

    public function __construct(
        BookingService $bookingService,
        GoogleCalendarService $googleCalendarService,
        EventAvailabilityService $eventAvailabilityService,
        NotificationService $notificationService
    ) {
        $this->bookingService = $bookingService;
        $this->googleCalendarService = $googleCalendarService;
        $this->eventAvailabilityService = $eventAvailabilityService;
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        $bookedEvents = Booking::with('event')->get();

        return view('bookings.index', compact('bookedEvents'));
    }

    public function store(CreateBookingRequest $request, $eventId)
    {
        $event = Event::findOrFail($eventId);
        $bookingTimezone = $request->getBookingTimezone();
        $bookingDate = $request->getBookingDate();
        $bookingTime = $request->getBookingTime();

        // Check for existing booking in Google Calendar
        $timeSlot = $this->eventAvailabilityService->getTimeSlotAvailability(
            $bookingTimezone,
            $bookingDate,
            $bookingTime,
            $event->duration
        );

        // If selected timeslot is not available, redirect back to page with the next 3 available time slots
        if (!$timeSlot['isAvailable']) {
            $params['event'] = $eventId;
            $params['booking_date'] = $bookingDate;
            $params['next_slots'] = $timeSlot['nextSlots'] ?? $timeSlot['nextSlots'];

            $errors['time_slot_unavailable'] = 'The selected date and time conflicts with an existing event.';

            if ($timeSlot['isMovedToNextDay']) {
                $params['booking_date'] = Carbon::parse($bookingDate)->addDay()->format('Y-m-d');
                unset($params['next_slots']);

                $date = Carbon::parse($bookingDate)->format('m-d-Y');
                $errors = [
                    'moved_to_next_day' =>
                        "{$errors['time_slot_unavailable']} 
                        No more available slots for {$date} after {$bookingTime}. 
                        Date is now adjusted to the next day (or you may select another date).",
                ];
            }

            return redirect()
                ->route('bookings.create', $params)
                ->withErrors($errors);
        }

        $bookingTimezone = $request->getBookingTimezone();
        $dateTime = Carbon::parse("$bookingDate $bookingTime", $bookingTimezone);

        // Prepare event data for Google Calendar
        $eventData = [
            'title' => $event->name,
            'description' => $event->description,
            'attendee_name' => $request->getAttendeeName(),
            'attendee_email' => $request->getAttendeeEmail(),
            'start' => $dateTime->toIso8601String(),
            'end' => $dateTime->copy()->addMinutes($event->duration)->toIso8601String(),
        ];

        // Add event to Google Calendar
        $eventAdded = $this->googleCalendarService->createEventToGoogleCalendar((object) $eventData);

        if ($eventAdded) {
            $formData = [
                'event_id' => $request->getEventId(),
                'booking_timezone' => $request->getBookingTimezone(),
                'booking_date' => $bookingDate,
                'booking_time' => $bookingTime,
                'duration' => $event->duration,
                'attendee_name' => $request->getAttendeeName(),
                'attendee_email' => $request->getAttendeeEmail(),
            ];

            // Add event to database
            $booking = $this->bookingService->createBooking($formData);

            // Send email for booking confirmation and event reminder
            if ($booking) {
                $this->notificationService->sendConfirmationEmail($booking);
                $this->notificationService->sendEventReminder($booking);
            }

            return view('bookings.thank-you', ['booking' => $booking]);
        }

        return redirect()
            ->route('bookings.create', ['event' => $eventId])
            ->withErrors([
                'error' => 'Unable to create event. Please try again.'
            ]);
    }

    public function create(Request $request, $eventId)
    {
        $selectedTimezone = $request->input('booking_timezone', config('app.timezone'));
        $selectedDate = $request->input('booking_date', now()->toDateString());

        $emptyTimeSlots = '';

        $event = Event::findOrFail($eventId);
        $timeSlots = $this->bookingService->generateTimeSlots($selectedDate, $selectedTimezone, $eventId);

        if (!$timeSlots) {
            $date = Carbon::parse($selectedDate)->format('m-d-Y');
            $emptyTimeSlots = "No more available time slots for {$date}. Please choose another date.";
        }

        $dateToday = Carbon::now()->format('Y-m-d');
        $timezones = DateTimeZone::listIdentifiers();

        return view('bookings.calendar', compact(
            'event',
            'selectedDate',
            'timeSlots',
            'dateToday',
            'timezones',
            'emptyTimeSlots'
        ));
    }

    public function destroy($eventId)
    {
        $this->bookingService->deleteBooking($eventId);
        
        return redirect()->route('bookings.index');
    }
}
