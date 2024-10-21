<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition()
    {
        return [
            'name' => fake()->word,
            'duration' => fake()->numberBetween(30, 180),
            'description' => fake()->sentence,
        ];
    }
}
