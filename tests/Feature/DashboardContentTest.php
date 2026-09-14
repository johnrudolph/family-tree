<?php

use App\Models\User;

test('the dashboard greets the user and links to their own person page', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee($user->person->fullName())
        ->assertSee(route('people.show', $user->person), false);
});
