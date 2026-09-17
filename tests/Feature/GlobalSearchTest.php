<?php

use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

test('the global search palette lists every person', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    Livewire::actingAs($viewer)
        ->test('pages::global-search')
        ->assertSee('Ada Lovelace');
});

test('selecting a person in the global search redirects to their page', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::global-search')
        ->call('goToPerson', $person->id)
        ->assertRedirect(route('people.show', $person));
});

test('the sidebar no longer links to the generic starter-kit repo or docs', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('github.com/laravel/livewire-starter-kit', false)
        ->assertDontSee('laravel.com/docs/starter-kits', false);
});
