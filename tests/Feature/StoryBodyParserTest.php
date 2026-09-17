<?php

use App\Models\Person;
use App\Support\StoryBodyParser;

test('a matching [[Name]] tag renders as a link to the person page', function () {
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    $html = StoryBodyParser::render('<p>[[Jane Drexler]] got married.</p>');

    expect($html)
        ->toContain('href="'.route('people.show', $person).'"')
        ->toContain('>Jane Drexler</a>')
        ->not->toContain('[[');
});

test('matching is case-insensitive', function () {
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    $html = StoryBodyParser::render('<p>[[jane drexler]] got married.</p>');

    expect($html)->toContain('href="'.route('people.show', $person).'"');
});

test('a tag with no matching person renders as plain bracketed text, not a link', function () {
    $html = StoryBodyParser::render('<p>[[Nobody Real]] showed up.</p>');

    expect($html)
        ->toContain('[[Nobody Real]]')
        ->not->toContain('<a ');
});

test('taggedPersonIds resolves multiple distinct tags and ignores duplicates', function () {
    $jane = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);
    $bob = Person::factory()->create(['first_name' => 'Bob', 'last_name' => 'Smith']);

    $ids = StoryBodyParser::taggedPersonIds('<p>[[Jane Drexler]] and [[Bob Smith]] met. [[Jane Drexler]] smiled.</p>');

    expect($ids->all())->toBe([$jane->id, $bob->id]);
});

test('taggedPersonIds ignores unmatched tags', function () {
    Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    $ids = StoryBodyParser::taggedPersonIds('<p>[[Jane Drexler]] and [[Nobody Real]].</p>');

    expect($ids)->toHaveCount(1);
});
