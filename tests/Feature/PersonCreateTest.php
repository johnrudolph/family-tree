<?php

use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

test('an admin can add a person without an invite', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::people.create')
        ->set('first_name', 'Grandma')
        ->set('last_name', 'Jones')
        ->call('save')
        ->assertHasNoErrors();

    $person = Person::query()->where('first_name', 'Grandma')->firstOrFail();
    expect($person->creator->id)->toBe($admin->id);
    expect($person->isEditor($admin))->toBeTrue();
});

test('a non-admin cannot add a person', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($user)
        ->test('pages::people.create')
        ->assertForbidden();
});
