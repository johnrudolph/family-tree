<?php

use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use Livewire\Livewire;

test('the banner shows living people whose birthday is today', function () {
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
        ->test('pages::on-this-day-banner')
        ->assertSee('Happy birthday')
        ->assertSee($birthdayPerson->fullName())
        ->assertSee('href="'.route('people.show', $birthdayPerson).'"', false)
        ->assertDontSee('NotToday');
});

test('a deceased person born on this day shows as a historical birth, not a "happy birthday"', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    // The factory links every user to a person with a random dob — neutralize
    // it so it can't coincidentally land on today's month/day and flake this.
    $viewer->person->update(['dob' => null]);
    $ancestor = Person::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Ancestor',
        'is_living' => false,
        'dob' => now()->subYears(120),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::on-this-day-banner')
        ->assertDontSee('Happy birthday')
        ->assertSee('Born on this day')
        ->assertSee($ancestor->fullName());
});

test('a person who died on this day in a past year shows a remembrance line', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create([
        'first_name' => 'Departed',
        'last_name' => 'Relative',
        'is_living' => false,
        'dod' => now()->subYears(5),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::on-this-day-banner')
        ->assertSee('we lost')
        ->assertSee($person->fullName());
});

test('an exact-dated story that happened on this day in a past year is shown', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create([
        'title' => 'The Big Trip',
        'start_date' => now()->subYears(10),
        'start_date_precision' => 'exact',
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::on-this-day-banner')
        ->assertSee('The Big Trip');
});

test('a year-only story is never matched, even if its Jan 1 placeholder date is today', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    Story::factory()->create([
        'title' => 'Some Year, Sometime',
        'start_date' => now()->startOfYear(),
        'start_date_precision' => 'year',
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::on-this-day-banner')
        ->assertDontSee('Some Year, Sometime');
});

test('the banner renders nothing when there is nothing to show today', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    // The factory links every user to a person with a random dob — neutralize
    // it so it can't coincidentally land on today's month/day and flake this.
    $viewer->person->update(['dob' => null]);
    Person::factory()->create(['is_living' => true, 'dob' => now()->subYears(30)->subDay()]);

    Livewire::actingAs($viewer)
        ->test('pages::on-this-day-banner')
        ->assertDontSee('Happy birthday')
        ->assertDontSee('Born on this day')
        ->assertDontSee('we lost');
});
