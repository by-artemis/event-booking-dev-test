<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Event;
use App\Models\Booking;

class BookingFeatureTest extends TestCase
{
    public function __construct($name = 'GoogleCalendarTest')
    {
        parent::__construct($name);
        $this->createApplication();
    }

    public function setup(): void
    {
        parent::setup();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testUserCanCreateBooking()
    {
        // 
    }

    public function testBookingCollisionDetection() {}
}
