<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Story::uniqueSlugFor($title),
            'body' => fake()->paragraphs(3, true),
            'start_date' => fake()->dateTimeBetween('-80 years', 'now')->format('Y-m-d'),
            'start_date_precision' => 'exact',
            'created_by' => User::factory(),
        ];
    }
}
