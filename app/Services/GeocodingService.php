<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * Free-text location search against OpenStreetMap's Nominatim geocoder —
     * free, and its ODbL license allows the results to be cached/stored
     * permanently (unlike Google's geocoding terms), which is what lets us
     * normalize and keep these for the map.
     *
     * Results are candidates for a human to pick from, never auto-applied.
     *
     * @return array<int, array{formatted_address: string, latitude: float, longitude: float, precision: string, city: ?string, region: ?string, country: ?string, country_code: ?string, external_ref: string}>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $response = Http::withHeaders(['User-Agent' => config('services.nominatim.user_agent')])
            ->get(self::ENDPOINT, [
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 5,
            ]);

        if ($response->failed()) {
            Log::warning('Nominatim geocoding lookup failed.', ['status' => $response->status(), 'query' => $query]);

            return [];
        }

        $candidates = [];

        foreach ($response->json() ?? [] as $result) {
            if (isset($result['lat'], $result['lon'], $result['osm_type'], $result['osm_id'])) {
                $candidates[] = $this->normalize($result);
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{formatted_address: string, latitude: float, longitude: float, precision: string, city: ?string, region: ?string, country: ?string, country_code: ?string, external_ref: string}
     */
    private function normalize(array $result): array
    {
        $address = $result['address'] ?? [];

        return [
            'formatted_address' => $result['display_name'],
            'latitude' => (float) $result['lat'],
            'longitude' => (float) $result['lon'],
            'precision' => $this->precisionFor($result['addresstype'] ?? $result['type'] ?? null),
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? null,
            'region' => $address['state'] ?? null,
            'country' => $address['country'] ?? null,
            'country_code' => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
            'external_ref' => "{$result['osm_type']}:{$result['osm_id']}",
        ];
    }

    private function precisionFor(?string $type): string
    {
        return match ($type) {
            'house', 'building', 'amenity', 'road', 'residential' => 'address',
            'state', 'region' => 'state',
            'country' => 'country',
            default => 'city',
        };
    }
}
