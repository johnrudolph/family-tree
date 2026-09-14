<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
use Livewire\Livewire;

test('searching finds a person by name', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    Person::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Person::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->set('search', 'Ada')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
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

test('a non-editor does not see the add relationship form for the selected person', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->assertDontSee('Add a relationship');
});
