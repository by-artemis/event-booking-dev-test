<?php

namespace Tests\Unit;

use Mockery;
use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Event;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\GoogleCalendarService;
use App\Services\EventAvailabilityService;
use Illuminate\Foundation\Testing\WithFaker;
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
    private GoogleCalendarService $calendarService;
    private EventAvailabilityService $eventAvailabilityService;

    private $googleClientMock;
    private $googleCalendarServiceMock;
    private $bookingServiceMock;
    private $calendarServiceMock;

    private $prophet;

    public function __construct($name = 'BookingTest')
    {
        parent::__construct($name);
        $this->createApplication();
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->bookingService = new BookingService(new Booking);
        $this->calendarService = new GoogleCalendarService();
        $this->eventAvailabilityService = new EventAvailabilityService($this->calendarService);
    }

    // public function tearDown(): void
    // {
    //     parent::tearDown();

    //     Mockery::close();

    //     self::$booking->event->delete();
    //     self::$booking->delete();
    // }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $faker = \Faker\Factory::create();

        self::$booking = Booking::factory()->create();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Mockery::close();

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

    public function testCheckTimeSlotAvailability(): void
    {
        $duration = 60;
        $selectedTime = '14:00:00';
        $data = [
            'start' => Carbon::now()->addDays(2),
            'end' => Carbon::now()->addDays(2)->addMinutes($duration),
            'timezone' => 'Asia/Manila'
        ];

        // Mock GoogleCalendarService
        $this->calendarServiceMock = Mockery::mock($this->calendarService);

        // Arrange: Mock the behavior of the GoogleCalendarService
        $this->calendarServiceMock
            ->shouldReceive('authenticate')
            ->once();

        // Arrange: Mock the behavior of the GoogleCalendarService
        $this->calendarServiceMock
            ->shouldReceive('getClient')
            ->once();

        // Mock hasConflictingEvents to return an empty array (no conflicts)
        $this->calendarServiceMock
            ->shouldReceive('getCalendarEvents')
            ->with($data)
            ->andReturn([]);

        // Inject the mocked GoogleCalendarService into EventAvailabilityService
        $eventAvailabilityService = new EventAvailabilityService($this->calendarServiceMock);

        // Act: Call the method
        $result = $eventAvailabilityService->getTimeSlotAvailability(
            'Asia/Manila',
            Carbon::now()->addDays(2)->format('Y-m-d'),
            $selectedTime,
            $duration
        );

        // Assert: Check the expected result
        $this->assertEquals(['isAvailable' => true], $result);
    }
}
