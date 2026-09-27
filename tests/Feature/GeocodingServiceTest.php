<?php

use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;

test('search normalizes a Nominatim response into a candidate list', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Portland, Multnomah County, Oregon, United States',
                'lat' => '45.5152',
                'lon' => '-122.6784',
                'osm_type' => 'relation',
                'osm_id' => 123,
                'addresstype' => 'city',
                'address' => [
                    'city' => 'Portland',
                    'state' => 'Oregon',
                    'country' => 'United States',
                    'country_code' => 'us',
                ],
            ],
        ]),
    ]);

    $results = app(GeocodingService::class)->search('Portland, OR');

    expect($results)->toHaveCount(1);
    expect($results[0])->toMatchArray([
        'formatted_address' => 'Portland, Multnomah County, Oregon, United States',
        'latitude' => 45.5152,
        'longitude' => -122.6784,
        'precision' => 'city',
        'city' => 'Portland',
        'region' => 'Oregon',
        'country' => 'United States',
        'country_code' => 'US',
        'external_ref' => 'relation:123',
    ]);
});

test('search returns nothing for a blank query without calling the geocoder', function () {
    Http::fake();

    $results = app(GeocodingService::class)->search('   ');

    expect($results)->toBe([]);
    Http::assertNothingSent();
});

test('search returns nothing when the geocoder request fails', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response(null, 500),
    ]);

    $results = app(GeocodingService::class)->search('Nowhere');

    expect($results)->toBe([]);
});
