<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\Story;
use App\Support\MapSerializer;

test('a birth with a geocoded location produces a map point', function () {
    $location = Location::factory()->create(['city' => 'Portland', 'region' => 'OR']);
    $person = Person::factory()->create(['dob' => '1954-03-03', 'birth_location_id' => $location->id]);

    $point = collect(MapSerializer::points())->first(fn ($p) => str_contains($p['title'], $person->fullName()) && $p['type'] === 'birth');

    expect($point)->not->toBeNull();
    expect($point['latitude'])->toBe($location->latitude);
    expect($point['longitude'])->toBe($location->longitude);
    expect($point['location_label'])->toBe('Portland, OR');
});

test('a person with no location produces no map point', function () {
    $person = Person::factory()->create(['dob' => '1954-03-03']);

    $points = collect(MapSerializer::points())->filter(fn ($p) => str_contains($p['title'], $person->fullName()));

    expect($points)->toBeEmpty();
});

test('a story with a location produces a map point', function () {
    $location = Location::factory()->create(['city' => 'Austin', 'region' => 'TX']);
    $story = Story::factory()->create(['title' => 'A Trip South', 'location_id' => $location->id]);

    $point = collect(MapSerializer::points())->firstWhere('title', $story->title);

    expect($point)->not->toBeNull();
    expect($point['type'])->toBe('story');
    expect($point['location_label'])->toBe('Austin, TX');
});
