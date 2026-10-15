<?php

use App\Models\Person;
use App\Models\Photo;
use App\Models\User;
use Livewire\Livewire;

test('opening the lightbox loads that photo and its form state', function () {
    $alice = Person::factory()->create();
    $photo = Photo::factory()->create(['description' => 'A sunny day']);
    $photo->people()->attach($alice->id);

    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->assertSet('photoId', $photo->id)
        ->assertSet('description', 'A sunny day')
        ->assertSet('person_ids', [$alice->id]);
});

test('closing clears the photo and context', function () {
    $photo = Photo::factory()->create();
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->call('close')
        ->assertSet('photoId', null)
        ->assertSet('contextIds', []);
});

test('next and previous cycle through the context ids, wrapping at the ends', function () {
    $photos = Photo::factory()->count(3)->create();
    $ids = $photos->pluck('id')->all();
    $user = User::factory()->withTwoFactor()->create();

    $component = Livewire::actingAs($user)
        ->test('photo-lightbox')
        ->call('open', $ids[0], $ids);

    $component->call('next')->assertSet('photoId', $ids[1]);
    $component->call('next')->assertSet('photoId', $ids[2]);
    $component->call('next')->assertSet('photoId', $ids[0]); // wraps forward
    $component->call('previous')->assertSet('photoId', $ids[2]); // wraps backward
});

test('a single-photo context does not move on next or previous', function () {
    $photo = Photo::factory()->create();
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->call('next')
        ->assertSet('photoId', $photo->id);
});

test('an admin editing the description autosaves without an explicit save action', function () {
    $photo = Photo::factory()->create();
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->set('description', 'Updated on the fly');

    expect($photo->fresh()->description)->toBe('Updated on the fly');
});

test('an admin tagging a person in the lightbox autosaves the tag', function () {
    $photo = Photo::factory()->create();
    $person = Person::factory()->create();
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->set('person_ids', [$person->id]);

    expect($photo->fresh()->people()->pluck('people.id')->all())->toBe([$person->id]);
});

test('an invalid date field does not block tagging a person, or vice versa', function () {
    $photo = Photo::factory()->create();
    $person = Person::factory()->create();
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        // "Year only" picked but no year typed yet — invalid/incomplete on
        // its own, and previously validated as part of every autosave,
        // including this unrelated tagging change.
        ->set('hasDate', true)
        ->set('date_precision', 'year')
        ->set('person_ids', [$person->id]);

    expect($photo->fresh()->people()->pluck('people.id')->all())->toBe([$person->id]);
});

test('a non-owner, non-admin cannot edit and their changes are not saved', function () {
    $photo = Photo::factory()->create(['description' => 'Original']);
    $stranger = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($stranger)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->assertSet('canEdit', false)
        ->set('description', 'Hijacked');

    expect($photo->fresh()->description)->toBe('Original');
});

test('the uploader can edit their own photo', function () {
    $uploader = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);

    Livewire::actingAs($uploader)
        ->test('photo-lightbox')
        ->call('open', $photo->id, [$photo->id])
        ->assertSet('canEdit', true);
});
