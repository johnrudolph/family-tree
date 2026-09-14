<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
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
