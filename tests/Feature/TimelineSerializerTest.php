<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\Photo;
use App\Models\Relationship;
use App\Models\Story;
use App\Support\TimelineSerializer;
use Illuminate\Support\Facades\Storage;

test('a person with a dob and birth city produces a birth event', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Drexler',
        'dob' => '1954-03-03',
        'dob_precision' => 'exact',
        'birth_location_id' => Location::factory()->create(['city' => 'Portland', 'region' => 'OR'])->id,
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
        'death_location_id' => Location::factory()->create(['city' => 'Austin', 'region' => 'TX'])->id,
    ]);

    $event = collect(TimelineSerializer::events())->first(fn ($e) => $e['person_id'] === $person->id && $e['type'] === 'death');

    expect($event['date'])->toBe('2020-01-15');
    expect($event['title'])->toBe('Jane Drexler dies in Austin, TX');
});

test('a death event carries the death date\'s own precision, not a hardcoded one', function () {
    $person = Person::factory()->create(['is_living' => false, 'dod' => '2020-01-01', 'dod_precision' => 'year']);

    $event = collect(TimelineSerializer::events())->first(fn ($e) => $e['person_id'] === $person->id && $e['type'] === 'death');

    expect($event['date_precision'])->toBe('year');
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
    $photo = Photo::create();
    $photo->addMediaFromString(fakeImageBytes())->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('file');
    $story->photos()->attach($photo->id);
    $story->update(['featured_photo_id' => $photo->id]);

    $event = collect(TimelineSerializer::events())->firstWhere('story_id', $story->id);

    expect($event['featured_image_url'])->not->toBeNull();
});

test('a story\'s full gallery is included for the timeline\'s lightbox', function () {
    Storage::fake('public');

    $story = Story::factory()->create();
    $first = Photo::create();
    $first->addMediaFromString(fakeImageBytes())->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('file');
    $second = Photo::create();
    $second->addMediaFromString(fakeImageBytes())->usingFileName('b.jpg')->preservingOriginal()->toMediaCollection('file');
    $story->photos()->attach([$first->id, $second->id]);

    $event = collect(TimelineSerializer::events())->firstWhere('story_id', $story->id);

    expect($event['gallery_urls'])->toHaveCount(2);
});

test('birth and death events have an empty gallery_urls, not null', function () {
    $person = Person::factory()->create(['dob' => '1950-01-01']);

    $event = collect(TimelineSerializer::events())->firstWhere('person_id', $person->id);

    expect($event['gallery_urls'])->toBe([]);
});

test('a spouse relationship with a marriage date produces a marriage event', function () {
    $husband = Person::factory()->create(['first_name' => 'John', 'last_name' => 'Belz']);
    $wife = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Belz']);
    $relationship = Relationship::factory()->create([
        'person_a_id' => $husband->id,
        'person_b_id' => $wife->id,
        'started_at' => '1980-06-14',
        'started_at_precision' => 'exact',
        'location_id' => Location::factory()->create(['city' => 'Omaha', 'region' => 'NE'])->id,
    ]);

    $event = collect(TimelineSerializer::events())->firstWhere('relationship_id', $relationship->id);

    expect($event['type'])->toBe('marriage');
    expect($event['date'])->toBe('1980-06-14');
    expect($event['title'])->toBe('John Belz and Jane Belz get married');
    expect($event['location']['label'])->toBe('Omaha, NE');
});

test('a divorced spouse relationship with an end date produces a separate divorce event', function () {
    $husband = Person::factory()->create();
    $wife = Person::factory()->create();
    $relationship = Relationship::factory()->create([
        'person_a_id' => $husband->id,
        'person_b_id' => $wife->id,
        'status' => 'divorced',
        'started_at' => '1980-06-14',
        'started_at_precision' => 'exact',
        'ended_at' => '1995-02-20',
        'ended_at_precision' => 'exact',
    ]);

    $events = collect(TimelineSerializer::events())->where('relationship_id', $relationship->id);

    expect($events->pluck('type')->sort()->values()->all())->toBe(['divorce', 'marriage']);
    expect($events->firstWhere('type', 'divorce')['date'])->toBe('1995-02-20');
});

test('a relationship with no marriage date produces no timeline event', function () {
    $relationship = Relationship::factory()->create(['started_at' => null]);

    $events = collect(TimelineSerializer::events())->where('relationship_id', $relationship->id);

    expect($events)->toBeEmpty();
});

test('a marriage event\'s featured image comes from its first attached photo', function () {
    Storage::fake('public');

    $relationship = Relationship::factory()->create(['started_at' => '1980-06-14', 'started_at_precision' => 'exact']);
    $photo = Photo::create();
    $photo->addMediaFromString(fakeImageBytes())->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('file');
    $relationship->photos()->attach($photo->id);

    $event = collect(TimelineSerializer::events())->firstWhere('relationship_id', $relationship->id);

    expect($event['featured_image_url'])->not->toBeNull();
    expect($event['gallery_urls'])->toHaveCount(1);
});
