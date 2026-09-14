<?php

use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use App\Services\PageEditorService;
use Livewire\Livewire;

test('an owner can add a co-editor to a person page', function () {
    $owner = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $owner);

    $newEditor = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($owner)
        ->test('pages::people.editors', ['person' => $person])
        ->set('newEditorUserId', $newEditor->id)
        ->call('addEditor')
        ->assertHasNoErrors();

    expect($person->isEditor($newEditor))->toBeTrue();
});

test('the last remaining editor of a person page cannot be removed via the UI', function () {
    $owner = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $owner);

    Livewire::actingAs($owner)
        ->test('pages::people.editors', ['person' => $person])
        ->call('removeEditor', $owner->id);

    expect($person->isEditor($owner))->toBeTrue();
});

test('an owner can add a co-editor to a story page', function () {
    $owner = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create();
    app(PageEditorService::class)->grantOwner($story, $owner);

    $newEditor = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($owner)
        ->test('pages::stories.editors', ['story' => $story])
        ->set('newEditorUserId', $newEditor->id)
        ->call('addEditor')
        ->assertHasNoErrors();

    expect($story->isEditor($newEditor))->toBeTrue();
});
