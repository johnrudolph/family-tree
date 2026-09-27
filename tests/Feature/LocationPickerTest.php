<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('searching finds candidates and selecting one persists and dispatches a location', function () {
    $user = User::factory()->withTwoFactor()->create();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Portland, Multnomah County, Oregon, United States',
                'lat' => '45.5152',
                'lon' => '-122.6784',
                'osm_type' => 'relation',
                'osm_id' => 123,
                'addresstype' => 'city',
                'address' => ['city' => 'Portland', 'state' => 'Oregon', 'country' => 'United States', 'country_code' => 'us'],
            ],
        ]),
    ]);

    $component = Livewire::actingAs($user)
        ->test('location-picker', ['field' => 'birth'])
        ->set('query', 'Portland')
        ->assertSet('results.0.formatted_address', 'Portland, Multnomah County, Oregon, United States');

    $component->call('select', 0)
        ->assertDispatched('location-selected', field: 'birth');

    $location = Location::query()->where('external_ref', 'relation:123')->firstOrFail();

    $component->assertSet('locationId', $location->id);
    expect(Location::query()->count())->toBe(1);
});

test('a query shorter than three characters clears results without searching', function () {
    $user = User::factory()->withTwoFactor()->create();

    Http::fake();

    Livewire::actingAs($user)
        ->test('location-picker', ['field' => 'birth'])
        ->set('query', 'Po')
        ->assertSet('results', []);

    Http::assertNothingSent();
});

test('clearing a selected location resets the picker and dispatches null', function () {
    $user = User::factory()->withTwoFactor()->create();
    $location = Location::factory()->create();

    Livewire::actingAs($user)
        ->test('location-picker', ['field' => 'death', 'locationId' => $location->id])
        ->assertSet('selectedLabel', $location->formatted_address)
        ->call('clear')
        ->assertSet('locationId', null)
        ->assertDispatched('location-selected', field: 'death', locationId: null);
});
