<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\Story;
use App\Models\User;
use Livewire\Livewire;

test('a member can view the timeline page', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $this->actingAs($viewer)
        ->get(route('timeline.index'))
        ->assertOk()
        ->assertSee('Timeline');
});

test('a guest cannot view the timeline page', function () {
    $this->get(route('timeline.index'))->assertRedirect(route('login'));
});

test('the timeline page includes birth and story events in its data', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler', 'dob' => '1954-03-03']);
    Story::factory()->create(['title' => 'A Notable Day', 'start_date' => '1990-06-01']);

    $response = $this->actingAs($viewer)->get(route('timeline.index'));

    $response->assertOk()
        ->assertSee('Jane Drexler is born', false)
        ->assertSee('A Notable Day', false);
});

test('toggling "only show my direct relatives" hides everyone else\'s events', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $sibling = Person::factory()->create(['first_name' => 'Sibling', 'dob' => '1990-01-01']);
    $stranger = Person::factory()->create(['first_name' => 'Stranger', 'dob' => '1990-01-01']);
    Relationship::create(['person_a_id' => $viewer->person_id, 'person_b_id' => $sibling->id, 'type' => 'sibling']);

    $component = Livewire::actingAs($viewer)
        ->test('pages::timeline.index')
        ->set('directRelativesOnly', true);

    $names = collect($component->instance()->events())->pluck('title')->implode(' ');

    expect($names)->toContain('Sibling');
    expect($names)->not->toContain('Stranger');
});
