<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Relationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relationship>
 */
class RelationshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_a_id' => Person::factory(),
            'person_b_id' => Person::factory(),
            'type' => 'spouse',
            'status' => 'married',
        ];
    }

    public function parentChild(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'parent_child',
            'status' => null,
        ]);
    }

    public function sibling(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sibling',
            'status' => null,
        ]);
    }
}
