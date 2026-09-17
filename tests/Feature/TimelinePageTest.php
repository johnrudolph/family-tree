<?php

use App\Models\Person;
use App\Models\Story;
use App\Models\User;

test('a member can view the timeline page', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $this->actingAs($viewer)
        ->get(route('timeline.index'))
        ->assertOk()
        ->assertSee('Timeline');
});

test('a guest cannot view the timeline page', function () {
    $this->get(route('timeline.index'))->assertRedirect(route('login'));
});

test('the timeline page includes birth and story events in its data', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Jane', 'last_name' => 'Drexler', 'dob' => '1954-03-03']);
    Story::factory()->create(['title' => 'A Notable Day', 'start_date' => '1990-06-01']);

    $response = $this->actingAs($viewer)->get(route('timeline.index'));

    $response->assertOk()
        ->assertSee('Jane Drexler is born', false)
        ->assertSee('A Notable Day', false);
});
