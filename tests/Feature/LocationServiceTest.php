<?php

use App\Models\Location;
use App\Services\LocationService;

test('findOrCreateFromCandidate creates a new location', function () {
    $location = app(LocationService::class)->findOrCreateFromCandidate([
        'formatted_address' => 'Portland, OR, United States',
        'latitude' => 45.5152,
        'longitude' => -122.6784,
        'precision' => 'city',
        'city' => 'Portland',
        'region' => 'Oregon',
        'country' => 'United States',
        'country_code' => 'US',
        'external_ref' => 'relation:123',
    ]);

    expect($location->exists)->toBeTrue();
    expect($location->source)->toBe('nominatim');
    expect(Location::query()->count())->toBe(1);
});

test('findOrCreateFromCandidate reuses an existing location with the same external ref', function () {
    $existing = Location::factory()->create(['external_ref' => 'relation:123']);

    $location = app(LocationService::class)->findOrCreateFromCandidate([
        'formatted_address' => 'Portland, OR, United States',
        'latitude' => 45.5152,
        'longitude' => -122.6784,
        'precision' => 'city',
        'city' => 'Portland',
        'region' => 'Oregon',
        'country' => 'United States',
        'country_code' => 'US',
        'external_ref' => 'relation:123',
    ]);

    expect($location->id)->toBe($existing->id);
    expect(Location::query()->count())->toBe(1);
});
