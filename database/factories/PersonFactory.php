<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'dob' => fake()->dateTimeBetween('-90 years', '-1 years'),
            'dob_precision' => 'exact',
            'is_living' => true,
            'created_by' => User::factory(),
        ];
    }

    public function deceased(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_living' => false,
            'dod' => fake()->dateTimeBetween($attributes['dob'] ?? '-90 years', 'now'),
        ]);
    }

    public function consented(): static
    {
        return $this->state(fn (array $attributes) => [
            'consented_at' => now(),
        ]);
    }
}
