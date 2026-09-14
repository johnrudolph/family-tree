<?php

use App\Models\Invite;
use App\Models\Person;
use App\Models\User;
use App\Notifications\PersonInvited;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admins can invite a new person by creating them and sending an invite', function () {
    Notification::fake();

    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::invites.invite-person')
        ->set('createNewPerson', true)
        ->set('first_name', 'Ada')
        ->set('last_name', 'Lovelace')
        ->set('email', 'ada@example.com')
        ->call('sendInvite')
        ->assertHasNoErrors();

    $invite = Invite::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($invite->person->fullName())->toBe('Ada Lovelace');
    Notification::assertSentOnDemand(PersonInvited::class);
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
