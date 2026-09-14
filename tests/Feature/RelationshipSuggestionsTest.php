<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
use Livewire\Livewire;

test('adding a spouse defaults to also marking them as parent of existing children', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $mom = Person::factory()->create();
    $child = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($mom, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $child->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $mom])
        ->set('type', 'spouse')
        ->assertSet('alsoParentOfChildIds', [$child->id])
        ->set('mode', 'new')
        ->set('new_first_name', 'Dad')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $dad = Person::query()->where('first_name', 'Dad')->firstOrFail();
    expect($child->fresh()->parents()->pluck('id'))->toContain($dad->id);
});

test('unchecking a step-child pill leaves that child unlinked', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $mom = Person::factory()->create();
    $child = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($mom, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $child->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $mom])
        ->set('type', 'spouse')
        ->set('alsoParentOfChildIds', [])
        ->set('mode', 'new')
        ->set('new_first_name', 'Stepdad')
        ->call('addRelationship');

    $stepdad = Person::query()->where('first_name', 'Stepdad')->firstOrFail();
    expect($child->fresh()->parents()->pluck('id'))->not->toContain($stepdad->id);
});

test('adding a child defaults to also marking existing spouses as parent', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $mom = Person::factory()->create();
    $dad = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($mom, $editor);
    Relationship::factory()->create(['person_a_id' => $mom->id, 'person_b_id' => $dad->id, 'type' => 'spouse']);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $mom])
        ->set('type', 'child')
        ->assertSet('alsoCoParentIds', [$dad->id])
        ->set('mode', 'new')
        ->set('new_first_name', 'Kid')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $kid = Person::query()->where('first_name', 'Kid')->firstOrFail();
    expect($kid->parents()->pluck('id'))->toContain($mom->id, $dad->id);
});

test('adding a sibling links them to both existing parents', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $mom = Person::factory()->create();
    $dad = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($me, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $dad->id, 'person_b_id' => $me->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $me])
        ->set('type', 'sibling')
        ->set('mode', 'new')
        ->set('new_first_name', 'Sibling')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $sibling = Person::query()->where('first_name', 'Sibling')->firstOrFail();
    expect($sibling->parents()->pluck('id'))->toContain($mom->id, $dad->id);
});

test('the sibling option is not offered for a person with no recorded parents', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $person])
        ->assertDontSee('Sibling of');
});
