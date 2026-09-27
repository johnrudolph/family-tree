<?php

use App\Models\Location;
use App\Models\Person;

test('a living person with everything filled in has no gaps', function () {
    $location = Location::factory()->create();
    $person = Person::factory()->create(['dob' => '1954-03-03', 'birth_location_id' => $location->id, 'is_living' => true]);

    expect($person->missingCoreDataFields())->toBe([]);
});

test('a living person missing dob and birth location reports both, and death fields never apply', function () {
    $person = Person::factory()->create(['dob' => null, 'birth_location_id' => null, 'is_living' => true]);

    expect($person->missingCoreDataFields())->toBe(['dob', 'birth_location']);
});

test('a deceased person also gets flagged for missing death date and location', function () {
    $person = Person::factory()->create([
        'dob' => '1900-01-01',
        'birth_location_id' => Location::factory()->create()->id,
        'is_living' => false,
        'dod' => null,
        'death_location_id' => null,
    ]);

    expect($person->missingCoreDataFields())->toBe(['dod', 'death_location']);
});

test('fields marked unknown no longer count as missing', function () {
    $person = Person::factory()->create([
        'dob' => null,
        'dob_unknown' => true,
        'birth_location_id' => null,
        'birth_location_unknown' => true,
        'is_living' => false,
        'dod' => null,
        'dod_unknown' => true,
        'death_location_id' => null,
        'death_location_unknown' => true,
    ]);

    expect($person->missingCoreDataFields())->toBe([]);
});

test('the missingCoreData scope matches the same people missingCoreDataFields would flag', function () {
    $complete = Person::factory()->create(['dob' => '1954-01-01', 'birth_location_id' => Location::factory()->create()->id]);
    $missingDob = Person::factory()->create(['dob' => null]);
    $unknownDob = Person::factory()->create(['dob' => null, 'dob_unknown' => true, 'birth_location_id' => Location::factory()->create()->id]);
    $missingDeath = Person::factory()->create(['dob' => '1900-01-01', 'birth_location_id' => Location::factory()->create()->id, 'is_living' => false]);

    $ids = Person::query()->missingCoreData()->pluck('id');

    expect($ids)->toContain($missingDob->id);
    expect($ids)->toContain($missingDeath->id);
    expect($ids)->not->toContain($complete->id);
    expect($ids)->not->toContain($unknownDob->id);
});
