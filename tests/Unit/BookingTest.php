<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Event;
use App\Models\Booking;
use App\Services\BookingService;
use Google\Client as GoogleClient;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Session;
use App\Services\EventAvailabilityService;
use Illuminate\Foundation\Testing\WithFaker;
use Google\Service\Calendar as GoogleCalendar;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BookingTest extends TestCase
{
    use WithFaker;
    use WithoutMiddleware;
    use DatabaseTransactions;

    private static Event $event;
    private static Booking $booking;

    private BookingService $bookingService;
    private GoogleCalendarService $googleCalendarService;
    private EventAvailabilityService $eventAvailabilityService;

    private GoogleCalendar $googleCalendar;

    private $googleClientMock;
    protected $bookingMock;
    private $accessToken;
    private $events;

    public function __construct($name = 'BookingTest')
    {
        parent::__construct($name);
        $this->createApplication();
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->googleCalendarService = new GoogleCalendarService();
        $this->eventAvailabilityService = new EventAvailabilityService($this->googleCalendarService);
        $this->bookingService = new BookingService(new Booking);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$booking = Booking::factory()->create();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        self::$booking->event->delete();
        self::$booking->delete();
    }

    public function testBookingHasEvent()
    {
        $booking = self::$booking;
        $this->assertInstanceOf(Event::class, $booking->event);
    }

    public function testCreateBookingSuccess()
    {
        $bookingData = [
            'event_id' => self::$booking->event->id,
            'booking_timezone' => self::$booking->booking_timezone,
            'booking_date' => self::$booking->booking_date,
            'booking_time' => self::$booking->booking_time,
            'duration' => self::$booking->event->duration,
            'attendee_name' => self::$booking->attendee_name,
            'attendee_email' => self::$booking->attendee_email,
        ];

        $actualResult = $this->bookingService->createBooking($bookingData);

        $this->assertInstanceOf(Booking::class, $actualResult);
        $this->assertDatabaseHas('bookings', [
            'event_id' => self::$booking->event->id,
        ]);
    }

    public function testCreateBookingFailure(): void
    {
        $invalidBookingData = [
            'event_id' => null,
            'booking_timezone' => '',
            'booking_date' => Carbon::now()->format('m-d-Y'),
            'booking_time' => Carbon::now()->format('H'),
        ];

        $this->expectException(\Exception::class); // Expect an exception to be thrown
        $this->bookingService->createBooking($invalidBookingData);
    }

    public function testDeleteBookingSuccess(): void
    {
        $bookingId = self::$booking->id;
        $deletedBooking = $this->bookingService->deleteBooking($bookingId);

        $this->assertTrue($deletedBooking);
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId]);
    }
}
