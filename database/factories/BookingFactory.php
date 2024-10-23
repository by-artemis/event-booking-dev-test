<?php

namespace Database\Factories;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition()
    {
        return [
            'event_id' => Event::factory(),
            'booking_time' => '8:00:00',
            'booking_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'booking_timezone' => fake()->timezone(),
            'attendee_name' => fake()->firstName(),
            'attendee_email' => fake()->safeEmail()
        ];
    }
}
