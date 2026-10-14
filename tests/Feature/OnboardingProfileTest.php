<?php

use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the security onboarding page explains who can see personal information here', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('onboarding.security'))
        ->assertSee('There is personal information on this site. The only users on this site are family members.');
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

test('a non-admin user can set their own profile photo during onboarding', function () {
    Storage::fake('public');

    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($user)
        ->test('pages::onboarding.profile')
        ->set('first_name', 'Jonathan')
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $person = $user->fresh()->person;
    expect(Photo::count())->toBe(1);
    expect($person->avatar_photo_id)->not->toBeNull();
    expect($person->photos()->count())->toBe(1);
});

test('the new story email preference defaults to on during onboarding, and can be turned off', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding.profile')
        ->assertSet('notify_on_new_stories', true)
        ->set('first_name', 'Jonathan')
        ->set('notify_on_new_stories', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->notify_on_new_stories)->toBeFalse();
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
