<?php

use App\Models\User;

test('a member can view the map page', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $this->actingAs($viewer)
        ->get(route('map.index'))
        ->assertOk()
        ->assertSee('Map');
});

test('a guest cannot view the map page', function () {
    $this->get(route('map.index'))->assertRedirect(route('login'));
});
