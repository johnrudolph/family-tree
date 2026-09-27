<?php

namespace App\Services;

use App\Models\Location;

class LocationService
{
    /**
     * Persists a geocoder search result as a normalized Location, reusing
     * an existing row for the same place (by the geocoder's own id) rather
     * than creating a duplicate every time someone picks it.
     *
     * @param  array<string, mixed>  $candidate  a normalized result from GeocodingService::search()
     */
    public function findOrCreateFromCandidate(array $candidate): Location
    {
        return Location::query()->firstOrCreate(
            ['external_ref' => $candidate['external_ref']],
            [
                'formatted_address' => $candidate['formatted_address'],
                'latitude' => $candidate['latitude'],
                'longitude' => $candidate['longitude'],
                'precision' => $candidate['precision'],
                'city' => $candidate['city'],
                'region' => $candidate['region'],
                'country' => $candidate['country'],
                'country_code' => $candidate['country_code'],
                'source' => 'nominatim',
            ],
        );
    }
}
