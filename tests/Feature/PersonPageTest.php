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

test('the person page shows the viewer\'s relationship to the person, but not to themself', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $parent = Person::factory()->create();
    Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $viewer->person->id]);

    $this->actingAs($viewer)
        ->get(route('people.show', $parent))
        ->assertOk()
        ->assertSee('Your parent');

    $this->actingAs($viewer)
        ->get(route('people.show', $viewer->person))
        ->assertOk()
        ->assertDontSee('Your parent');
});

test('the person page links to viewing that person centered in the family tree', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    $this->actingAs($viewer)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertSee(route('tree.index', ['person' => $person->id]), false);
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

test('an admin can set birth and death cities and they show on the person page', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['is_living' => false]);

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('birth_city', 'Portland, OR')
        ->set('death_city', 'Austin, TX')
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->birth_city)->toBe('Portland, OR');
    expect($person->death_city)->toBe('Austin, TX');

    $this->actingAs($admin)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertSee('Portland, OR')
        ->assertSee('Austin, TX');
});

test('editing a person\'s bio sanitizes the rich text before saving', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('bio', '<p>hello</p><script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->bio)->toBe('<p>hello</p>');
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

test('the manage-relationships list is collapsed by default and expands on toggle', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    $relationship = Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $person->id]);

    $component = Livewire::actingAs($editor)->test('pages::people.show', ['person' => $person]);

    // The remove button is unique to the expanded list — the mini family
    // tree widget embeds every name in its JSON payload regardless, so a
    // plain assertSee on a name wouldn't actually prove the list is hidden.
    $component->assertSet('showManageRelationships', false)
        ->assertDontSeeHtml("removeRelationship({$relationship->id})");

    $component->set('showManageRelationships', true)
        ->assertSeeHtml("removeRelationship({$relationship->id})");
});

test('adding a relationship dispatches a family-widget-updated event for the mini tree', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $other = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
        ->set('relType', 'spouse')
        ->set('relMode', 'existing')
        ->set('relExistingPersonId', $other->id)
        ->call('addRelationship')
        ->assertDispatched('family-widget-updated');
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

test('a new person created inline from the person page can be marked deceased with a date of death', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
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
