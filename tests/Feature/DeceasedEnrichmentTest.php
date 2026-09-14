<?php

use App\Models\Person;
use App\Models\User;
use App\Services\PageEditorService;
use Livewire\Livewire;

test('an editor cannot manage enrichment for a living person who is not themself', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['is_living' => true]);
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.enrich', ['person' => $person])
        ->assertForbidden();
});

test('an editor can manage enrichment for a deceased person', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->deceased()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.enrich', ['person' => $person])
        ->set('contact_email', 'memorial@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->contact_email)->toBe('memorial@example.com');
});

test('editing enrichment on behalf of a deceased person does not set consented_at', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->deceased()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.enrich', ['person' => $person])
        ->set('phone', '555-0100')
        ->call('save');

    expect($person->fresh()->consented_at)->toBeNull();
});

test('a non-editor still cannot manage enrichment for a deceased person', function () {
    $stranger = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->deceased()->create();

    Livewire::actingAs($stranger)
        ->test('pages::people.enrich', ['person' => $person])
        ->assertForbidden();
});

test('a deceased person with a photo shows it without needing consent', function () {
    $person = Person::factory()->deceased()->create();

    expect($person->canShowEnrichment())->toBeTrue();
});

test('middle name is included in the full name and can be edited', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Lovelace']);
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)
        ->test('pages::people.edit', ['person' => $person])
        ->set('middle_name', 'King')
        ->call('save')
        ->assertHasNoErrors();

    $person->refresh();
    expect($person->middle_name)->toBe('King');
    expect($person->fullName())->toBe('Ada King Lovelace');
});
