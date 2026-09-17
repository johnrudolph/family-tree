<?php

use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

test('the birthday banner shows living people whose birthday is today', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $birthdayPerson = Person::factory()->create([
        'first_name' => 'Birthday',
        'last_name' => 'Person',
        'is_living' => true,
        'dob' => now()->subYears(30),
    ]);
    Person::factory()->create([
        'first_name' => 'NotToday',
        'is_living' => true,
        'dob' => now()->subYears(30)->subDay(),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::birthday-banner')
        ->assertSee('Happy birthday')
        ->assertSee($birthdayPerson->fullName())
        ->assertDontSee('NotToday');
});

test('the birthday banner ignores deceased people even if the date matches', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    Person::factory()->create([
        'first_name' => 'Deceased',
        'is_living' => false,
        'dob' => now()->subYears(80),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::birthday-banner')
        ->assertDontSee('Happy birthday');
});
