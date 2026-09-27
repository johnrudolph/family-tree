<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

test('a non-admin cannot view the data gaps page', function () {
    $member = User::factory()->withTwoFactor()->create();

    $this->actingAs($member)
        ->get(route('admin.data-gaps'))
        ->assertForbidden();
});

test('an admin sees people with missing core data and not people who are complete', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $gappy = Person::factory()->create(['first_name' => 'Gappy', 'sex' => 'male', 'dob' => null]);
    $complete = Person::factory()->create([
        'first_name' => 'Complete',
        'sex' => 'male',
        'dob' => '1954-01-01',
        'birth_location_id' => Location::factory()->create()->id,
    ]);

    $ids = Livewire::actingAs($admin)
        ->test('pages::admin.data-gaps')
        ->instance()
        ->people();

    $ids = collect($ids)->pluck('person.id');

    expect($ids)->toContain($gappy->id);
    expect($ids)->not->toContain($complete->id);
});

test('opening a person shows only their actual gaps', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create([
        'dob' => '1954-01-01',
        'birth_location_id' => null,
        'is_living' => true,
    ]);

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.data-gaps')
        ->call('edit', $person->id);

    $component->assertSet('editingPersonId', $person->id);
    $component->assertSee('Birth location');
    $component->assertDontSee('Date of death');
});

test('filling in a gap saves it and the person drops off the list', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['sex' => 'male', 'dob' => null, 'birth_location_id' => Location::factory()->create()->id]);

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.data-gaps')
        ->call('edit', $person->id)
        ->set('editDob', '1954-03-03')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->dob?->toDateString())->toBe('1954-03-03');

    $ids = collect($component->instance()->people())->pluck('person.id');
    expect($ids)->not->toContain($person->id);
});

test('marking a field unknown clears it from the gaps list without setting a fake value', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['sex' => 'male', 'dob' => null, 'birth_location_id' => Location::factory()->create()->id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.data-gaps')
        ->call('edit', $person->id)
        ->set('editDobUnknown', true)
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->dob)->toBeNull();
    expect($person->dob_unknown)->toBeTrue();
    expect($person->missingCoreDataFields())->toBe([]);
});

test('a missing sex can be filled in or marked unknown', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $person = Person::factory()->create(['sex' => null, 'dob' => '1954-01-01', 'birth_location_id' => Location::factory()->create()->id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.data-gaps')
        ->call('edit', $person->id)
        ->assertSee('Sex')
        ->set('editSex', 'female')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->sex)->toBe('female');
});

test('a non-admin cannot mount the data gaps component directly', function () {
    $member = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($member)
        ->test('pages::admin.data-gaps')
        ->assertForbidden();
});
