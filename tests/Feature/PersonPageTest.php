<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
use App\Services\RevisionService;
use Livewire\Livewire;

test('a member can view a person page', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    $this->actingAs($viewer)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertSee($person->fullName());
});

test('relationships are shown on a person page', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $parent = Person::factory()->create(['first_name' => 'Parent']);
    $child = Person::factory()->create(['first_name' => 'Child']);
    Relationship::factory()->parentChild()->create([
        'person_a_id' => $parent->id,
        'person_b_id' => $child->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('people.show', $child))
        ->assertOk()
        ->assertSee('Parent');
});

test('a non-editor cannot access the person edit page', function () {
    $viewer = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::people.edit', ['person' => $person])
        ->assertForbidden();
});

test('an admin can edit a person\'s core facts', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('first_name', 'Updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->first_name)->toBe('Updated');
});

test('only the linked user can manage their own enrichment fields and doing so records consent', function () {
    $user = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $user->update(['person_id' => $person->id]);

    Livewire::actingAs($user)
        ->test('pages::people.enrich', ['person' => $person])
        ->set('contact_email', 'me@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->contact_email)->toBe('me@example.com');
    expect($person->hasConsented())->toBeTrue();
});

test('a user cannot manage enrichment fields for someone else', function () {
    $user = User::factory()->withTwoFactor()->create();
    $otherPerson = Person::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::people.enrich', ['person' => $otherPerson])
        ->assertForbidden();
});

test('a person without a linked account shows a no-account badge and explanation', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['person' => $person])
        ->assertSee('No account')
        ->assertSee('Only the family member themself can add profile pictures and contact information');
});

test('a person with a linked account who edits their own page shows account and editor badges', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $linkedUser = User::factory()->withTwoFactor()->create();
    $linkedUser->update(['person_id' => $person->id]);
    app(PageEditorService::class)->grantOwner($person, $linkedUser);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['person' => $person])
        ->assertSee('Has account')
        ->assertSee('Editor');
});

test('an editor can add a sibling relationship directly from the person page', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $me = Person::factory()->create();
    $mom = Person::factory()->create();
    $dad = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($me, $editor);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $me->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $dad->id, 'person_b_id' => $me->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $me])
        ->set('relType', 'sibling')
        ->set('relMode', 'new')
        ->set('relNewFirstName', 'Sibling')
        ->call('addRelationship')
        ->assertHasNoErrors();

    $sibling = Person::query()->where('first_name', 'Sibling')->firstOrFail();
    expect($sibling->parents()->pluck('id'))->toContain($mom->id, $dad->id);
});

test('an editor can remove a relationship directly from the person page', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    $relationship = Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $person->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
        ->call('removeRelationship', $relationship->id)
        ->assertHasNoErrors();

    expect($person->fresh()->parents()->pluck('id'))->not->toContain($parent->id);
});

test('an editor can expand the editors and history sections on the person page', function () {
    $editor = User::factory()->withTwoFactor()->create(['name' => 'Editor Name']);
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    app(RevisionService::class)->record($person, $editor, ['first_name' => $person->first_name]);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
        ->set('showEditors', true)
        ->assertSee('Editor Name')
        ->set('showHistory', true)
        ->assertSee('Current');
});
