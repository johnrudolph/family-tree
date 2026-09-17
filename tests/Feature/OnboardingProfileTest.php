<?php

use App\Models\User;
use Livewire\Livewire;

test('the security onboarding page explains who can see personal information here', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('onboarding.security'))
        ->assertSee('There is personal information on this site. The only users on this site are family members who have opted in to share their information.');
});

test('a user without 2FA cannot reach the profile onboarding step directly', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.profile'))
        ->assertRedirect(route('onboarding.security'));
});

test('a secured user can reach the profile onboarding step and sees the visibility notice', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('onboarding.profile'))
        ->assertOk()
        ->assertSee('Your profile picture and contact information are optional, and will be visible to all family members.');
});

test('saving the profile onboarding step updates core facts, enrichment fields, and records consent', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding.profile')
        ->set('first_name', 'Jonathan')
        ->set('preferred_name', 'Johnny')
        ->set('use_preferred_name_everywhere', true)
        ->set('dob', '1990-01-01')
        ->set('contact_email', 'me@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $person = $user->fresh()->person;
    expect($person->first_name)->toBe('Jonathan');
    expect($person->fullName())->toBe('Johnny');
    expect($person->dob?->toDateString())->toBe('1990-01-01');
    expect($person->contact_email)->toBe('me@example.com');
    expect($person->consented_at)->not->toBeNull();
});

test('skipping the profile onboarding step makes no changes', function () {
    $user = User::factory()->withTwoFactor()->create();
    $originalName = $user->person->first_name;

    Livewire::actingAs($user)
        ->test('pages::onboarding.profile')
        ->set('first_name', 'Should Not Save')
        ->call('skip');

    expect($user->fresh()->person->first_name)->toBe($originalName);
});
