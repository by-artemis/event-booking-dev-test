<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Event;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanCreateBooking()
    {
        $event = Event::factory()->create();

        $bookingDate = Carbon::now()->addDay();
        $bookingTime = $bookingDate->addHour();

        $response = $this->post("/events/{$event->id}/book", [
            'event_id' => $event->id,
            'booking_date' => $bookingDate->format('Y-m-d'),
            'booking_time' => $bookingTime->format('H:i'),
            'attendee_email' => fake()->safeEmail(),
            'attendee_name' => fake()->firstName(),
        ]);

        $response->assertSee('Thank You!');
        $response->assertSee($event->name);
    }

    public function testBookingCollisionDetection() {}
}
