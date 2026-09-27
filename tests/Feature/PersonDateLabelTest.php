<?php

use App\Models\Person;

test('an exact dob shows the full date', function () {
    $person = Person::factory()->create(['dob' => '1954-03-03', 'dob_precision' => 'exact']);

    expect($person->dobLabel())->toBe('March 3, 1954');
});

test('a year-only dob shows just the year', function () {
    $person = Person::factory()->create(['dob' => '1954-01-01', 'dob_precision' => 'year']);

    expect($person->dobLabel())->toBe('1954');
});

test('a null dob has no label', function () {
    $person = Person::factory()->create(['dob' => null]);

    expect($person->dobLabel())->toBeNull();
});

test('an exact dod shows the full date, a year-only dod shows just the year', function () {
    $exact = Person::factory()->create(['is_living' => false, 'dod' => '2020-06-15', 'dod_precision' => 'exact']);
    $yearOnly = Person::factory()->create(['is_living' => false, 'dod' => '2020-01-01', 'dod_precision' => 'year']);

    expect($exact->dodLabel())->toBe('June 15, 2020');
    expect($yearOnly->dodLabel())->toBe('2020');
});
