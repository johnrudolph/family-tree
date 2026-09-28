<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Story;
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

test('the browser tab title is the person\'s name', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    $this->actingAs($viewer)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertSee('Jane Drexler - '.config('app.name'), false);
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

test('an admin can set a person\'s sex', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['sex' => null]);

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('sex', 'female')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->sex)->toBe('female');
});

test('an admin can set a year-only birth date instead of an exact one', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['dob' => null]);

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('dob_precision', 'year')
        ->set('dob_year', '1901')
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->dob->toDateString())->toBe('1901-01-01');
    expect($person->dob_precision)->toBe('year');
    expect($person->dobLabel())->toBe('1901');
});

test('switching a person with an exact dob back to an empty year-only field clears it, not leaves the old date', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['dob' => '1954-03-03', 'dob_precision' => 'exact']);

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('dob_precision', 'year')
        ->set('dob_year', null)
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->dob)->toBeNull();
    expect($person->dob_precision)->toBe('unknown');
});

test('a person page shows stories they are tagged in via [[Name]], not just the curated "about" list', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);
    Story::factory()->create(['title' => 'Tagged Only Story', 'body' => '<p>[[Jane Drexler]] did a thing.</p>']);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['person' => $person])
        ->assertSee('Tagged Only Story');
});

test('an admin can set birth and death locations and they show on the person page', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['is_living' => false]);
    $birthLocation = Location::factory()->create(['city' => 'Portland', 'region' => 'OR']);
    $deathLocation = Location::factory()->create(['city' => 'Austin', 'region' => 'TX']);

    Livewire::actingAs($admin)
        ->test('pages::people.edit', ['person' => $person])
        ->set('birth_location_id', $birthLocation->id)
        ->set('death_location_id', $deathLocation->id)
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->birth_location_id)->toBe($birthLocation->id);
    expect($person->death_location_id)->toBe($deathLocation->id);

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

test('a person without a linked account shows a no-account badge', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['person' => $person])
        ->assertSee('No account');
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

test('the manage-relationships list is shown directly, without needing to expand it', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    $parent = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    $relationship = Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $person->id]);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
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

test('an editor sees the editors and history sections directly on the person page', function () {
    $editor = User::factory()->withTwoFactor()->create(['name' => 'Editor Name']);
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    app(RevisionService::class)->record($person, $editor, ['first_name' => $person->first_name]);

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
        ->assertSee('Editor Name')
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

test('a new person created inline from the person page can get a birth and death location right away', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);
    $birthLocation = Location::factory()->create();
    $deathLocation = Location::factory()->create();

    Livewire::actingAs($editor)
        ->test('pages::people.show', ['person' => $person])
        ->set('relType', 'child')
        ->set('relMode', 'new')
        ->set('relNewFirstName', 'Located')
        ->set('relNewBirthLocationId', $birthLocation->id)
        ->set('relNewIsLiving', false)
        ->set('relNewDeathLocationId', $deathLocation->id)
        ->call('addRelationship')
        ->assertHasNoErrors();

    $located = Person::query()->where('first_name', 'Located')->firstOrFail();
    expect($located->birth_location_id)->toBe($birthLocation->id);
    expect($located->death_location_id)->toBe($deathLocation->id);
});
