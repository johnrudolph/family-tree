<?php

use App\Models\Person;
use App\Models\Story;
use App\Support\TimelineSerializer;
use Illuminate\Support\Facades\Storage;

test('a person with a dob and birth city produces a birth event', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Drexler',
        'dob' => '1954-03-03',
        'dob_precision' => 'exact',
        'birth_city' => 'Portland, OR',
    ]);

    $event = collect(TimelineSerializer::events())->firstWhere('person_id', $person->id);

    expect($event['type'])->toBe('birth');
    expect($event['date'])->toBe('1954-03-03');
    expect($event['title'])->toBe('Jane Drexler is born in Portland, OR');
    expect($event['url'])->toBe(route('people.show', $person));
});

test('a person with no dob produces no birth event', function () {
    $person = Person::factory()->create(['dob' => null]);

    $events = collect(TimelineSerializer::events())->where('person_id', $person->id);

    expect($events)->toBeEmpty();
});

test('a deceased person with a death city produces a death event', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Drexler',
        'is_living' => false,
        'dod' => '2020-01-15',
        'death_city' => 'Austin, TX',
    ]);

    $event = collect(TimelineSerializer::events())->first(fn ($e) => $e['person_id'] === $person->id && $e['type'] === 'death');

    expect($event['date'])->toBe('2020-01-15');
    expect($event['title'])->toBe('Jane Drexler dies in Austin, TX');
});

test('a story without an end date is a point event, one with an end date is a span', function () {
    $dot = Story::factory()->create(['title' => 'A Single Day', 'start_date' => '1990-06-01']);
    $span = Story::factory()->create([
        'title' => 'A Long Trip',
        'start_date' => '1990-06-01',
        'end_date' => '1990-06-10',
        'end_date_precision' => 'exact',
    ]);

    $events = collect(TimelineSerializer::events());
    $dotEvent = $events->firstWhere('story_id', $dot->id);
    $spanEvent = $events->firstWhere('story_id', $span->id);

    expect($dotEvent['end_date'])->toBeNull();
    expect($spanEvent['end_date'])->toBe('1990-06-10');
});

test('events are sorted chronologically regardless of type', function () {
    Person::factory()->create(['first_name' => 'Oldest', 'dob' => '1900-01-01']);
    Story::factory()->create(['title' => 'Middle Story', 'start_date' => '1950-01-01']);
    Person::factory()->create(['first_name' => 'Newest', 'is_living' => false, 'dod' => '2000-01-01', 'dob' => null]);

    $dates = collect(TimelineSerializer::events())->pluck('date')->all();

    expect($dates)->toBe(collect($dates)->sort()->values()->all());
});

test('a story\'s featured image is included when one is set', function () {
    Storage::fake('public');

    $story = Story::factory()->create();
    $media = $story->addMediaFromString('fake-image-bytes')
        ->usingFileName('a.jpg')
        ->preservingOriginal()
        ->toMediaCollection('gallery');
    $story->featureImage($media);

    $event = collect(TimelineSerializer::events())->firstWhere('story_id', $story->id);

    expect($event['featured_image_url'])->not->toBeNull();
});
