<?php

namespace Tests\Unit;

use Request;
use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Event;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class BookingTest extends TestCase
{
    // use RefreshDatabase;
    use WithFaker;
    use WithoutMiddleware;

    private static Event $event;
    private static Booking $booking;
    private $service;

    public function __construct($name = 'BookingTest')
    {
        parent::__construct($name);

        $this->createApplication();

        $this->service = new BookingService(new Booking());
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $faker = \Faker\Factory::create();
        self::$booking = Booking::factory()
            ->create();
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

    // public function testCreate()
    // {
    //    
    // }
}
