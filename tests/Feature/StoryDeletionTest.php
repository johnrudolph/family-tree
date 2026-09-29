<?php

use App\Models\PageEditor;
use App\Models\Person;
use App\Models\Revision;
use App\Models\Story;
use App\Models\Suggestion;
use App\Models\User;
use App\Services\PageEditorService;
use App\Services\StoryDeletionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

test('deleting a story removes the story record', function () {
    $story = Story::factory()->create();

    app(StoryDeletionService::class)->delete($story);

    expect(Story::query()->find($story->id))->toBeNull();
});

test('deleting a story untags people without deleting them', function () {
    $person = Person::factory()->create();
    $story = Story::factory()->create();
    $story->people()->attach($person);

    app(StoryDeletionService::class)->delete($story);

    expect(Person::query()->find($person->id))->not->toBeNull();
});

test('deleting a story removes its page editors, revisions, and suggestions', function () {
    $story = Story::factory()->create();
    $user = User::factory()->create();

    PageEditor::create(['editable_type' => Story::class, 'editable_id' => $story->id, 'user_id' => $user->id, 'role' => 'owner']);
    Revision::create(['revisable_type' => Story::class, 'revisable_id' => $story->id, 'user_id' => $user->id, 'data' => ['title' => 'X']]);
    Suggestion::create(['suggestable_type' => Story::class, 'suggestable_id' => $story->id, 'user_id' => $user->id, 'payload' => ['title' => 'X'], 'status' => 'pending']);

    app(StoryDeletionService::class)->delete($story);

    expect(PageEditor::query()->where('editable_type', Story::class)->where('editable_id', $story->id)->count())->toBe(0);
    expect(Revision::query()->where('revisable_type', Story::class)->where('revisable_id', $story->id)->count())->toBe(0);
    expect(Suggestion::query()->where('suggestable_type', Story::class)->where('suggestable_id', $story->id)->count())->toBe(0);
});

test('deleting a story removes its gallery photos and audio', function () {
    Storage::fake('public');

    $story = Story::factory()->create();
    $story->addMediaFromString('fake-image-bytes')->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('gallery');
    $wavHeader = 'RIFF'.pack('V', 36).'WAVE'.'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8).'data'.pack('V', 0);
    $story->addMediaFromString($wavHeader)->usingFileName('a.wav')->preservingOriginal()->toMediaCollection('audio');

    expect(Media::query()->count())->toBe(2);

    app(StoryDeletionService::class)->delete($story);

    expect(Media::query()->count())->toBe(0);
});

test('an editor can delete a story from its page by typing the title to confirm', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create(['title' => 'The Move to Ohio']);
    app(PageEditorService::class)->grantOwner($story, $editor);

    Livewire::actingAs($editor)
        ->test('pages::stories.show', ['story' => $story])
        ->set('deleteConfirmationName', 'The Move to Ohio')
        ->call('deleteStory')
        ->assertHasNoErrors()
        ->assertRedirect(route('stories.index'));

    expect(Story::query()->find($story->id))->toBeNull();
});

test('typing the wrong title blocks story deletion', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create(['title' => 'The Move to Ohio']);
    app(PageEditorService::class)->grantOwner($story, $editor);

    Livewire::actingAs($editor)
        ->test('pages::stories.show', ['story' => $story])
        ->set('deleteConfirmationName', 'The Move to Ohi')
        ->call('deleteStory')
        ->assertHasErrors(['deleteConfirmationName']);

    expect(Story::query()->find($story->id))->not->toBeNull();
});

test('a non-editor, non-admin cannot delete a story', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $story = Story::factory()->create(['title' => 'The Move to Ohio']);

    Livewire::actingAs($user)
        ->test('pages::stories.show', ['story' => $story])
        ->set('deleteConfirmationName', 'The Move to Ohio')
        ->call('deleteStory')
        ->assertForbidden();

    expect(Story::query()->find($story->id))->not->toBeNull();
});

test('a non-editor does not see the delete option on a story page', function () {
    $member = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $story = Story::factory()->create();

    $this->actingAs($member)
        ->get(route('stories.show', $story))
        ->assertOk()
        ->assertDontSee('Delete story');
});
