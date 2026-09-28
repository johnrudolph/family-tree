<?php

namespace Database\Factories;

use App\Models\ChangeLogEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeLogEntry>
 */
class ChangeLogEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => $this->faker->sentence(),
            'merged_at' => $this->faker->dateTimeBetween('-6 months'),
        ];
    }
}
