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

    expect(Relationship::query()
        ->where('type', 'sibling')
        ->where(fn ($q) => $q->where('person_a_id', $me->id)->orWhere('person_b_id', $me->id))
        ->where(fn ($q) => $q->where('person_a_id', $sibling->id)->orWhere('person_b_id', $sibling->id))
        ->exists())->toBeTrue();
});

test('adding a sibling creates a real, removable relationship', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $other = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($me, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $me])
        ->set('type', 'sibling')
        ->set('mode', 'existing')
        ->set('existingPersonId', $other->id)
        ->call('addRelationship')
        ->assertHasNoErrors();

    $relationship = Relationship::query()->where('type', 'sibling')->firstOrFail();

    $component = Livewire::actingAs($editor)->test('pages::people.relationships', ['person' => $me]);
    $row = $component->instance()->relationshipRows()->firstWhere('id', $relationship->id);

    expect($row)->not->toBeNull();
    expect($row['label'])->toBe('Sibling');

    $component->call('removeRelationship', $relationship->id)->assertHasNoErrors();

    expect(Relationship::query()->where('id', $relationship->id)->exists())->toBeFalse();
    expect($me->fresh()->siblings()->pluck('id'))->not->toContain($other->id);
});

test('siblings merges explicit sibling relationships with shared-parent derivation, deduped', function () {
    $me = Person::factory()->create();
    $mom = Person::factory()->create();
    $explicitSibling = Person::factory()->create();
    $sharedParentSibling = Person::factory()->create();
    Relationship::factory()->sibling()->create(['person_a_id' => $me->id, 'person_b_id' => $explicitSibling->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $sharedParentSibling->id]);
    // Also give the explicit sibling the same shared parent, to prove no duplicate entry appears.
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $explicitSibling->id]);

    $siblingIds = $me->siblings()->pluck('id');

    expect($siblingIds)->toContain($explicitSibling->id, $sharedParentSibling->id);
    expect($siblingIds->duplicates())->toBeEmpty();
});

test('removing a sibling parent pill leaves that parent unlinked', function () {
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
        ->assertSet('alsoSiblingParentIds', [$mom->id, $dad->id])
        ->call('removeSuggestion', 'alsoSiblingParentIds', $dad->id)
        ->assertSet('alsoSiblingParentIds', [$mom->id])
        ->set('mode', 'new')
        ->set('new_first_name', 'Sibling')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $sibling = Person::query()->where('first_name', 'Sibling')->firstOrFail();
    expect($sibling->parents()->pluck('id'))->toContain($mom->id)
        ->and($sibling->parents()->pluck('id'))->not->toContain($dad->id);
});

test('adding a sibling previews who the new person will also become a sibling of', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $mom = Person::factory()->create();
    $existingSibling = Person::factory()->create(['first_name' => 'Existing', 'last_name' => 'Sibling']);
    app(PageEditorService::class)->grantOwner($me, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $existingSibling->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $me])
        ->set('type', 'sibling')
        ->assertSee('Existing Sibling');
});

test('the sibling option is offered even for a person with no recorded parents yet', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $person])
        ->assertSee('Sibling of');
});

test('adding a sibling with no recorded parents yet still creates the sibling relationship', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.relationships', ['person' => $person])
        ->set('type', 'sibling')
        ->set('mode', 'new')
        ->set('new_first_name', 'Sibling')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $sibling = Person::query()->where('first_name', 'Sibling')->firstOrFail();
    expect($person->fresh()->siblings()->pluck('id'))->toContain($sibling->id);
});

test('the existing-person picker excludes anyone already directly related', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $sister = Person::factory()->create(['first_name' => 'Sister']);
    $mom = Person::factory()->create(['first_name' => 'Mom']);
    $dad = Person::factory()->create(['first_name' => 'Dad']);
    $stranger = Person::factory()->create(['first_name' => 'Stranger']);
    app(PageEditorService::class)->grantOwner($sister, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $sister->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $dad->id, 'person_b_id' => $sister->id]);

    $component = Livewire::actingAs($editor)->test('pages::people.relationships', ['person' => $sister]);

    $candidateIds = $component->instance()->candidatePeople()->pluck('id');

    expect($candidateIds)->not->toContain($mom->id, $dad->id);
    expect($candidateIds)->toContain($stranger->id);
});

test('the existing-person picker excludes an already-derived sibling', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $mom = Person::factory()->create();
    $me = Person::factory()->create(['first_name' => 'Me']);
    $sister = Person::factory()->create(['first_name' => 'Sister']);
    app(PageEditorService::class)->grantOwner($me, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $sister->id]);

    $component = Livewire::actingAs($editor)->test('pages::people.relationships', ['person' => $me]);

    expect($component->instance()->candidatePeople()->pluck('id'))->not->toContain($sister->id);
});

test('siblings are derived from shared parents and shown even though no direct relationship is stored', function () {
    $mom = Person::factory()->create();
    $me = Person::factory()->create(['first_name' => 'Me']);
    $sister = Person::factory()->create(['first_name' => 'Sister']);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $sister->id]);

    expect($me->siblings()->pluck('id'))->toContain($sister->id);
    expect(Relationship::query()->where('person_a_id', $me->id)->orWhere('person_b_id', $me->id)->where(function ($q) use ($sister) {
        $q->where('person_a_id', $sister->id)->orWhere('person_b_id', $sister->id);
    })->exists())->toBeFalse();
});

test('a person with no recorded parents has no siblings', function () {
    $person = Person::factory()->create();

    expect($person->siblings())->toBeEmpty();
});
