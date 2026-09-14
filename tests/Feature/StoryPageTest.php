<?php

use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use Livewire\Livewire;

test('any member can create a story and tag people in it', function () {
    $user = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.create')
        ->set('title', 'The Move to Ohio')
        ->set('body', 'It was 1962…')
        ->set('person_ids', [$person->id])
        ->call('save')
        ->assertHasNoErrors();

    $story = Story::query()->where('title', 'The Move to Ohio')->firstOrFail();
    expect($story->people->pluck('id')->all())->toBe([$person->id]);
});

test('a story page shows tagged people and renders markdown', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create(['title' => 'A Family Tale', 'body' => '**bold** text']);

    $this->actingAs($viewer)
        ->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('A Family Tale')
        ->assertSee('<strong>bold</strong>', false);
});

test('a non-creator, non-admin cannot edit a story', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $story = Story::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.edit', ['story' => $story])
        ->assertForbidden();
});
