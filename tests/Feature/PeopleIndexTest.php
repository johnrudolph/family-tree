<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use Livewire\Livewire;

test('a member can view the people index', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler']);

    $this->actingAs($viewer)
        ->get(route('people.index'))
        ->assertOk()
        ->assertSee('Jane Drexler');
});

test('a guest cannot view the people index', function () {
    $this->get(route('people.index'))->assertRedirect(route('login'));
});

test('toggling "only show my direct relatives" hides everyone else', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $sibling = Person::factory()->create(['first_name' => 'Sibling', 'last_name' => 'Relative']);
    $stranger = Person::factory()->create(['first_name' => 'Stranger', 'last_name' => 'Unrelated']);
    Relationship::create(['person_a_id' => $viewer->person_id, 'person_b_id' => $sibling->id, 'type' => 'sibling']);

    $ids = Livewire::actingAs($viewer)
        ->test('pages::people.index')
        ->set('directRelativesOnly', true)
        ->instance()
        ->people()
        ->pluck('id');

    expect($ids)->toContain($sibling->id);
    expect($ids)->not->toContain($stranger->id);
});

test('the toggle is not shown to a viewer with no linked person', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $admin->update(['person_id' => null]);

    Livewire::actingAs($admin)
        ->test('pages::people.index')
        ->assertDontSee('Only show my direct relatives');
});
