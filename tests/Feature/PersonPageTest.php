<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
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
