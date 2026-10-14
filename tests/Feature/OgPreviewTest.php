<?php

use App\Models\Person;
use App\Models\Story;

test('a person\'s signed og share url is publicly reachable and redirects toward the real page', function () {
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    $this->get($person->ogShareUrl())
        ->assertOk()
        ->assertSee('og:title" content="Jane Drexler"', false)
        ->assertSee(route('people.show', $person), false);
});

test('a story\'s signed og share url is publicly reachable and redirects toward the real page', function () {
    $story = Story::factory()->create(['title' => 'A Notable Day']);

    $this->get($story->ogShareUrl())
        ->assertOk()
        ->assertSee('og:title" content="A Notable Day"', false)
        ->assertSee(route('stories.show', $story), false);
});

test('an unsigned og preview url is rejected', function () {
    $person = Person::factory()->create();

    $this->get(route('og.person.show', $person))->assertForbidden();
});

test('a person\'s signed og image url renders a png without authentication', function () {
    $person = Person::factory()->create();

    $response = $this->get($person->ogImageUrl());

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

test('a story\'s signed og image url renders a png without authentication', function () {
    $story = Story::factory()->create();

    $response = $this->get($story->ogImageUrl());

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

test('an unsigned og image url is rejected', function () {
    $story = Story::factory()->create();

    $this->get(route('og.story.image', $story))->assertForbidden();
});
