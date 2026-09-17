<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
use Livewire\Livewire;

test('the tree panel starts with the viewer themself selected', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->assertSet('selectedPersonId', $viewer->person->id)
        ->assertSee($viewer->person->fullName());
});

test('the tree defaults to centering on the root ancestor of the viewer\'s branch', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $grandparent = Person::factory()->create();
    $parent = Person::factory()->create();
    Relationship::factory()->parentChild()->create(['person_a_id' => $grandparent->id, 'person_b_id' => $parent->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $viewer->person->id]);

    $mainId = Livewire::actingAs($viewer)->test('pages::tree.index')->instance()->mainId();

    expect($mainId)->toBe($grandparent->id);
});

test('the tree centers on the viewer themself when they have no recorded parents', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $mainId = Livewire::actingAs($viewer)->test('pages::tree.index')->instance()->mainId();

    expect($mainId)->toBe($viewer->person->id);
});

test('the tree defaults to the widest connected family group, even if it isn\'t the viewer\'s own branch', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    // A much larger, unrelated family group the viewer isn't part of.
    $bigRoot = Person::factory()->create();
    $bigChild1 = Person::factory()->create();
    $bigChild2 = Person::factory()->create();
    $bigGrandchild = Person::factory()->create();
    Relationship::factory()->parentChild()->create(['person_a_id' => $bigRoot->id, 'person_b_id' => $bigChild1->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $bigRoot->id, 'person_b_id' => $bigChild2->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $bigChild1->id, 'person_b_id' => $bigGrandchild->id]);

    $mainId = Livewire::actingAs($viewer)->test('pages::tree.index')->instance()->mainId();

    expect($mainId)->toBe($bigRoot->id);
});

test('the search command palette lists every person for client-side filtering', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    Person::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Person::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

test('selecting a person shows their card and dispatches a center-on event', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->assertSee('Ada Lovelace')
        ->assertDispatched('tree-center-on', id: $person->id);
});

test('an editor of the selected person can add a relationship from the panel', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->set('relType', 'parent')
        ->set('relMode', 'existing')
        ->set('relExistingPersonId', $parent->id)
        ->call('addRelationship')
        ->assertHasNoErrors()
        ->assertDispatched('tree-data-updated');

    expect($person->fresh()->parents()->pluck('id'))->toContain($parent->id);
});

test('a new person created inline from the tree panel can have a date of birth set immediately', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->set('relType', 'child')
        ->set('relMode', 'new')
        ->set('relNewFirstName', 'Kid')
        ->set('relNewDob', '2010-05-01')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $kid = Person::query()->where('first_name', 'Kid')->firstOrFail();
    expect($kid->dob?->toDateString())->toBe('2010-05-01');
});

test('an editor can remove a relationship from the tree panel', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    $relationship = Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $person->id]);

    Livewire::actingAs($editor)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->call('removeRelationship', $relationship->id)
        ->assertHasNoErrors()
        ->assertDispatched('tree-data-updated');

    expect($person->fresh()->parents()->pluck('id'))->not->toContain($parent->id);
});

test('removing a sibling parent pill in the tree panel leaves that parent unlinked', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $mom = Person::factory()->create();
    $dad = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($me, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $dad->id, 'person_b_id' => $me->id]);

    Livewire::actingAs($editor)
        ->test('pages::tree.index')
        ->call('selectPerson', $me->id)
        ->set('relType', 'sibling')
        ->assertSet('relAlsoSiblingParentIds', [$mom->id, $dad->id])
        ->call('removeSuggestion', 'relAlsoSiblingParentIds', $dad->id)
        ->assertSet('relAlsoSiblingParentIds', [$mom->id])
        ->set('relMode', 'new')
        ->set('relNewFirstName', 'Sibling')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $sibling = Person::query()->where('first_name', 'Sibling')->firstOrFail();
    expect($sibling->parents()->pluck('id'))->toContain($mom->id)
        ->and($sibling->parents()->pluck('id'))->not->toContain($dad->id);
});

test('a new person created inline from the tree panel can be marked deceased with a date of death', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->set('relType', 'child')
        ->set('relMode', 'new')
        ->set('relNewFirstName', 'Departed')
        ->set('relNewIsLiving', false)
        ->set('relNewDod', '2020-03-15')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $departed = Person::query()->where('first_name', 'Departed')->firstOrFail();
    expect($departed->is_living)->toBeFalse();
    expect($departed->dod?->toDateString())->toBe('2020-03-15');
});

test('adding a sibling from the tree panel creates a real, removable relationship shown in the panel', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $other = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($me, $editor);

    Livewire::actingAs($editor)
        ->test('pages::tree.index')
        ->call('selectPerson', $me->id)
        ->set('relType', 'sibling')
        ->set('relMode', 'existing')
        ->set('relExistingPersonId', $other->id)
        ->call('addRelationship')
        ->assertHasNoErrors();

    $relationship = Relationship::query()->where('type', 'sibling')->firstOrFail();

    $component = Livewire::actingAs($editor)->test('pages::tree.index')->call('selectPerson', $me->id);
    $row = $component->instance()->selectedPersonRelationshipRows()->firstWhere('id', $relationship->id);
    expect($row)->not->toBeNull();
    expect($row['label'])->toBe('Sibling');

    $component->call('removeRelationship', $relationship->id)->assertHasNoErrors();
    expect(Relationship::query()->where('id', $relationship->id)->exists())->toBeFalse();
});

test('adding a second sibling without reselecting the type still links parents', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $mom = Person::factory()->create();
    $dad = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($me, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $dad->id, 'person_b_id' => $me->id]);

    $component = Livewire::actingAs($editor)->test('pages::tree.index')
        ->call('selectPerson', $me->id)
        ->set('relType', 'sibling')
        ->set('relMode', 'new')
        ->set('relNewFirstName', 'First')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $first = Person::query()->where('first_name', 'First')->firstOrFail();
    expect($first->parents()->pluck('id'))->toContain($mom->id, $dad->id);

    // Deliberately don't touch relType again — this reproduces adding a
    // second sibling from an already-open panel without re-clicking the
    // "Sibling" radio, which used to leave relAlsoSiblingParentIds stale
    // and empty after the first submission's reset.
    $component
        ->set('relNewFirstName', 'Second')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $second = Person::query()->where('first_name', 'Second')->firstOrFail();
    expect($second->parents()->pluck('id'))->toContain($mom->id, $dad->id);
});

test('a non-editor does not see the add relationship form for the selected person', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->assertDontSee('Add a relationship');
});
