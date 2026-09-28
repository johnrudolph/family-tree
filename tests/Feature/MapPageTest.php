<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use Livewire\Livewire;

test('a member can view the map page', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $this->actingAs($viewer)
        ->get(route('map.index'))
        ->assertOk()
        ->assertSee('Map');
});

test('a guest cannot view the map page', function () {
    $this->get(route('map.index'))->assertRedirect(route('login'));
});

test('toggling "only show my direct relatives" hides everyone else\'s points', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $location = Location::factory()->create();
    $sibling = Person::factory()->create(['first_name' => 'Sibling', 'dob' => '1990-01-01', 'birth_location_id' => $location->id]);
    $stranger = Person::factory()->create(['first_name' => 'Stranger', 'dob' => '1990-01-01', 'birth_location_id' => $location->id]);
    Relationship::create(['person_a_id' => $viewer->person_id, 'person_b_id' => $sibling->id, 'type' => 'sibling']);

    $component = Livewire::actingAs($viewer)
        ->test('pages::map.index')
        ->set('directRelativesOnly', true);

    $titles = collect($component->instance()->points())->pluck('title')->implode(' ');

    expect($titles)->toContain('Sibling');
    expect($titles)->not->toContain('Stranger');
});
