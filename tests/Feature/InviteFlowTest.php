<?php

use App\Models\Invite;
use App\Models\Person;
use App\Models\User;
use App\Notifications\PersonInvited;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admins can invite a living person already on the tree who has no account yet', function () {
    Notification::fake();

    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'is_living' => true]);

    Livewire::actingAs($admin)
        ->test('pages::invites.invite-person')
        ->set('existingPersonId', $person->id)
        ->set('email', 'ada@example.com')
        ->call('sendInvite')
        ->assertHasNoErrors();

    $invite = Invite::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($invite->person_id)->toBe($person->id);
    Notification::assertSentOnDemand(PersonInvited::class);
});

test('a deceased person is not invitable', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $deceased = Person::factory()->create(['is_living' => false]);

    $ids = Livewire::actingAs($admin)
        ->test('pages::invites.invite-person')
        ->instance()
        ->invitablePeople()
        ->pluck('id');

    expect($ids)->not->toContain($deceased->id);
});

test('a person who already has an account is not invitable', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $existingUser = User::factory()->create();

    $ids = Livewire::actingAs($admin)
        ->test('pages::invites.invite-person')
        ->instance()
        ->invitablePeople()
        ->pluck('id');

    expect($ids)->not->toContain($existingUser->person_id);
});

test('inviting without selecting a person fails validation', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::invites.invite-person')
        ->set('email', 'nobody@example.com')
        ->call('sendInvite')
        ->assertHasErrors(['existingPersonId']);
});

test('non-admins cannot access the invite page', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($user)
        ->test('pages::invites.invite-person')
        ->assertForbidden();
});

test('an invitee can accept a valid invite and create their account', function () {
    $admin = User::factory()->create();
    $person = Person::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'created_by' => $admin->id]);
    $invite = Invite::factory()->create([
        'person_id' => $person->id,
        'email' => 'grace@example.com',
        'invited_by' => $admin->id,
    ]);

    Livewire::test('pages::invites.accept', ['token' => $invite->token])
        ->set('name', 'Grace Hopper')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'grace@example.com')->firstOrFail();

    expect($user->person_id)->toBe($person->id);
    expect($invite->fresh()->isAccepted())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('an expired invite cannot be accepted', function () {
    $invite = Invite::factory()->expired()->create();

    Livewire::test('pages::invites.accept', ['token' => $invite->token])
        ->assertSee('no longer valid');
});
