<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
use App\Services\RelationshipService;
use Livewire\Livewire;

test('an editor can add a parent relationship to an existing person', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $child = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($child, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $child])
        ->set('type', 'parent')
        ->set('mode', 'existing')
        ->set('existingPersonId', $parent->id)
        ->call('addRelationship')
        ->assertHasNoErrors();

    expect($child->fresh()->parents()->pluck('id'))->toContain($parent->id);
});

test('adding a second parent automatically marries them to the existing parent', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $child = Person::factory()->create();
    $firstParent = Person::factory()->create();
    $secondParent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($child, $editor);
    app(RelationshipService::class)->addParentChild($firstParent, $child);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $child])
        ->set('type', 'parent')
        ->set('mode', 'existing')
        ->set('existingPersonId', $secondParent->id)
        ->call('addRelationship')
        ->assertHasNoErrors();

    $spouseLink = Relationship::query()->where('type', 'spouse')->first();

    expect($spouseLink)->not->toBeNull();
    expect($spouseLink->status)->toBe('married');
    expect([$spouseLink->person_a_id, $spouseLink->person_b_id])->toEqualCanonicalizing([$firstParent->id, $secondParent->id]);
});

test('the "mark as married" suggestion can be declined by removing the pill', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $child = Person::factory()->create();
    $firstParent = Person::factory()->create();
    $secondParent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($child, $editor);
    app(RelationshipService::class)->addParentChild($firstParent, $child);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $child])
        ->set('type', 'parent')
        ->set('mode', 'existing')
        ->set('existingPersonId', $secondParent->id)
        ->call('removeSuggestion', 'alsoMarriedToParentIds', $firstParent->id)
        ->call('addRelationship')
        ->assertHasNoErrors();

    expect(Relationship::query()->where('type', 'spouse')->exists())->toBeFalse();
});

test('an editor can add a spouse by creating a brand new person inline', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $person])
        ->set('type', 'spouse')
        ->set('mode', 'new')
        ->set('new_first_name', 'Sam')
        ->set('new_last_name', 'Smith')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $spouse = Person::query()->where('first_name', 'Sam')->firstOrFail();
    expect($person->fresh()->spouses()->pluck('id'))->toContain($spouse->id);
    expect($spouse->isEditor($editor))->toBeTrue();
});

test('a new person created inline can be marked deceased with a date of death', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $person])
        ->set('type', 'child')
        ->set('mode', 'new')
        ->set('new_first_name', 'Departed')
        ->set('new_is_living', false)
        ->set('new_dod', '2020-03-15')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $departed = Person::query()->where('first_name', 'Departed')->firstOrFail();
    expect($departed->is_living)->toBeFalse();
    expect($departed->dod?->toDateString())->toBe('2020-03-15');
});

test('a duplicate relationship is rejected gracefully', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $child = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($child, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $child->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $child])
        ->set('type', 'parent')
        ->set('mode', 'existing')
        ->set('existingPersonId', $parent->id)
        ->call('addRelationship');

    expect(Relationship::query()->where('person_a_id', $parent->id)->where('person_b_id', $child->id)->count())->toBe(1);
});

test('an editor can remove a relationship', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $child = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($child, $editor);
    $relationship = Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $child->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $child])
        ->call('removeRelationship', $relationship->id);

    expect(Relationship::query()->find($relationship->id))->toBeNull();
});

test('a non-editor cannot manage relationships', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $person = Person::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::people.relationships', ['person' => $person])
        ->assertForbidden();
});
