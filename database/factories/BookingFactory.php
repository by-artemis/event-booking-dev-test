<?php

namespace Database\Factories;

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
            'booking_time' => fake()->time('H:i'),
            'booking_date' => fake()->date('Y-m-d'),
            'booking_timezone' => fake()->timezone(),
            'attendee_name' => fake()->firstName(),
            'attendee_email' => fake()->safeEmail()
        ];
    }
}
