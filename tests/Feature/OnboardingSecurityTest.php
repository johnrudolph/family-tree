<?php

use App\Models\User;

test('a user without 2FA or a passkey is redirected from content routes to the onboarding page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertRedirect(route('onboarding.security'));
});

test('the onboarding page requires a recent password confirmation, like security settings does', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.security'))
        ->assertRedirect(route('password.confirm'));
});

test('the onboarding page is reachable once the password is confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('onboarding.security'))
        ->assertOk()
        ->assertSee('Secure your account');
});

test('a user with 2FA already enabled is bounced straight through onboarding to the dashboard', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('onboarding.security'))
        ->assertRedirect(route('dashboard'));
});

test('a user with a passkey already registered is not gated', function () {
    $user = User::factory()->create();
    $user->passkeys()->create([
        'name' => 'Test key',
        'credential_id' => 'cred-1',
        'credential' => json_encode(['type' => 'public-key']),
    ]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertOk();
});
