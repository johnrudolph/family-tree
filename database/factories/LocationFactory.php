<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();
        $region = fake()->randomElement(['OR', 'TX', 'CA', 'NY', 'WA', 'IL', 'CO', 'GA']);

        return [
            'formatted_address' => "{$city}, {$region}, United States",
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'precision' => 'city',
            'city' => $city,
            'region' => $region,
            'country' => 'United States',
            'country_code' => 'US',
            'source' => 'manual',
            'external_ref' => fake()->unique()->uuid(),
        ];
    }
}
