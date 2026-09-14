<?php

use App\Models\Person;
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

test('a non-editor does not see the add relationship form for the selected person', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::tree.index')
        ->call('selectPerson', $person->id)
        ->assertDontSee('Add a relationship');
});
