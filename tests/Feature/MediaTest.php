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
