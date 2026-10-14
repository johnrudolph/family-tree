<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-80 years', 'now')->format('Y-m-d'),
            'date_precision' => 'exact',
        ];
    }
}
