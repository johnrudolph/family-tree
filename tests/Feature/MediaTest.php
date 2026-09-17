<?php

use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use App\Services\PageEditorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a user can upload their own profile photo via the enrichment form', function () {
    $user = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $user->update(['person_id' => $person->id]);

    Livewire::actingAs($user)
        ->test('pages::people.enrich', ['person' => $person])
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->photoUrl())->not->toBeNull();
});

test('a user can add photos to a story\'s gallery while creating it', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.create')
        ->set('title', 'A Photographed Day')
        ->set('start_date', '2020-01-01')
        ->set('photos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $story = Story::query()->where('title', 'A Photographed Day')->firstOrFail();
    expect($story->galleryMedia())->toHaveCount(2);
});

test('an editor can add photos to a story gallery', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create();
    app(PageEditorService::class)->grantOwner($story, $editor);

    Livewire::actingAs($editor)
        ->test('pages::stories.edit', ['story' => $story])
        ->set('newPhotos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect($story->fresh()->galleryMedia())->toHaveCount(2);
});
