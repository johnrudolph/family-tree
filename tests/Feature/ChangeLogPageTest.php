<?php

use App\Models\ChangeLogEntry;
use App\Models\User;
use Livewire\Livewire;

test('a member can view the change log', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    ChangeLogEntry::factory()->create(['description' => 'Added a shiny new feature.', 'merged_at' => now()->subDay()]);

    $this->actingAs($viewer)
        ->get(route('changelog.index'))
        ->assertOk()
        ->assertSee('Added a shiny new feature.');
});

test('a guest cannot view the change log', function () {
    $this->get(route('changelog.index'))->assertRedirect(route('login'));
});

test('entries are shown most recently merged first', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $older = ChangeLogEntry::factory()->create(['description' => 'Older change.', 'merged_at' => now()->subWeek()]);
    $newer = ChangeLogEntry::factory()->create(['description' => 'Newer change.', 'merged_at' => now()->subDay()]);

    $ids = Livewire::actingAs($viewer)
        ->test('pages::changelog.index')
        ->instance()
        ->entries()
        ->pluck('id');

    expect($ids->first())->toBe($newer->id);
    expect($ids->last())->toBe($older->id);
});
