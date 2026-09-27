<?php

use App\Models\PageEditor;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Revision;
use App\Models\Story;
use App\Models\Suggestion;
use App\Models\User;
use App\Services\PageEditorService;
use App\Services\PersonDeletionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

test('deleting a person removes the person record', function () {
    $person = Person::factory()->create();

    app(PersonDeletionService::class)->delete($person);

    expect(Person::query()->find($person->id))->toBeNull();
});

test('deleting a person removes their relationships but leaves the other person alone', function () {
    $person = Person::factory()->create();
    $parent = Person::factory()->create();
    $child = Person::factory()->create();
    $spouse = Person::factory()->create();
    Relationship::create(['person_a_id' => $parent->id, 'person_b_id' => $person->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $person->id, 'person_b_id' => $child->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $person->id, 'person_b_id' => $spouse->id, 'type' => 'spouse', 'status' => 'married']);

    app(PersonDeletionService::class)->delete($person);

    expect(Relationship::query()->count())->toBe(0);
    expect(Person::query()->find($parent->id))->not->toBeNull();
    expect(Person::query()->find($child->id))->not->toBeNull();
    expect(Person::query()->find($spouse->id))->not->toBeNull();
});

test('deleting a person untags them from stories without deleting the stories', function () {
    $person = Person::factory()->create();
    $story = Story::factory()->create();
    $story->people()->attach($person);

    app(PersonDeletionService::class)->delete($person);

    expect(Story::query()->find($story->id))->not->toBeNull();
    expect($story->fresh()->people)->toBeEmpty();
});

test('deleting a person removes their page editors, revisions, and suggestions', function () {
    $person = Person::factory()->create();
    $user = User::factory()->create();

    PageEditor::create(['editable_type' => Person::class, 'editable_id' => $person->id, 'user_id' => $user->id, 'role' => 'owner']);
    Revision::create(['revisable_type' => Person::class, 'revisable_id' => $person->id, 'user_id' => $user->id, 'data' => ['first_name' => 'X']]);
    Suggestion::create(['suggestable_type' => Person::class, 'suggestable_id' => $person->id, 'user_id' => $user->id, 'payload' => ['first_name' => 'X'], 'status' => 'pending']);

    app(PersonDeletionService::class)->delete($person);

    expect(PageEditor::query()->where('editable_type', Person::class)->where('editable_id', $person->id)->count())->toBe(0);
    expect(Revision::query()->where('revisable_type', Person::class)->where('revisable_id', $person->id)->count())->toBe(0);
    expect(Suggestion::query()->where('suggestable_type', Person::class)->where('suggestable_id', $person->id)->count())->toBe(0);
});

test('deleting a person unlinks but does not delete their user account', function () {
    $user = User::factory()->create();
    $person = $user->person;

    app(PersonDeletionService::class)->delete($person);

    expect(User::query()->find($user->id))->not->toBeNull();
    expect($user->fresh()->person_id)->toBeNull();
});

test('deleting a person removes their uploaded photo', function () {
    Storage::fake('public');

    $person = Person::factory()->create();
    $person->addMediaFromString('fake-image-bytes')->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('photo');

    expect($person->getMedia('photo'))->toHaveCount(1);

    app(PersonDeletionService::class)->delete($person);

    expect(Media::query()->count())->toBe(0);
});

test('an admin can delete a person from their page by typing their name to confirm', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    Livewire::actingAs($admin)
        ->test('pages::people.show', ['person' => $person])
        ->set('deleteConfirmationName', 'Jane Drexler')
        ->call('deletePerson')
        ->assertHasNoErrors()
        ->assertRedirect(route('people.index'));

    expect(Person::query()->find($person->id))->toBeNull();
});

test('typing the wrong name blocks the deletion', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    Livewire::actingAs($admin)
        ->test('pages::people.show', ['person' => $person])
        ->set('deleteConfirmationName', 'Jane Drexle')
        ->call('deletePerson')
        ->assertHasErrors(['deleteConfirmationName']);

    expect(Person::query()->find($person->id))->not->toBeNull();
});

test('a page editor who is not an admin cannot delete a person', function () {
    $editor = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
        ->set('deleteConfirmationName', 'Jane Drexler')
        ->call('deletePerson')
        ->assertForbidden();

    expect(Person::query()->find($person->id))->not->toBeNull();
});

test('a non-admin does not see the delete option on a person page', function () {
    $member = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $person = Person::factory()->create();

    $this->actingAs($member)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertDontSee('Delete person');
});
