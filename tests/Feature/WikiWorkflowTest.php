<?php

use App\Models\Person;
use App\Models\User;
use App\Notifications\SuggestionReviewed;
use App\Notifications\SuggestionSubmitted;
use App\Services\PageEditorService;
use App\Services\SuggestionService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a non-editor can only submit a suggestion, not edit directly', function () {
    Notification::fake();

    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();
    app(PageEditorService::class)->grantOwner($person, $editor);

    $reader = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($reader)
        ->test('pages::people.edit', ['person' => $person])
        ->assertForbidden();

    Livewire::actingAs($reader)
        ->test('pages::people.suggest', ['person' => $person])
        ->set('first_name', 'Suggested Name')
        ->call('submit')
        ->assertHasNoErrors();

    expect($person->fresh()->first_name)->not->toBe('Suggested Name');
    expect($person->suggestions()->where('status', 'pending')->count())->toBe(1);
    Notification::assertSentTo($editor, SuggestionSubmitted::class);
});

test('an editor can merge a suggestion as-is, which updates the page and notifies the proposer', function () {
    Notification::fake();

    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Original']);
    app(PageEditorService::class)->grantOwner($person, $editor);

    $reader = User::factory()->withTwoFactor()->create();
    $suggestion = app(SuggestionService::class)->submit($person, $reader, ['first_name' => 'Merged Name']);

    Livewire::actingAs($editor)
        ->test('pages::people.suggestions', ['person' => $person])
        ->call('merge', $suggestion->id);

    expect($person->fresh()->first_name)->toBe('Merged Name');
    expect($suggestion->fresh()->status)->toBe('merged');
    expect($person->revisions()->count())->toBe(1);
    Notification::assertSentTo($reader, SuggestionReviewed::class);
});

test('an editor can merge a suggestion with their own changes', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Original']);
    app(PageEditorService::class)->grantOwner($person, $editor);

    $reader = User::factory()->withTwoFactor()->create();
    $suggestion = app(SuggestionService::class)->submit($person, $reader, ['first_name' => 'Proposed Name']);

    Livewire::actingAs($editor)
        ->test('pages::people.suggestions', ['person' => $person])
        ->call('editSuggestion', $suggestion->id)
        ->set('editPayload.first_name', 'Adjusted Name')
        ->call('mergeWithChanges');

    expect($person->fresh()->first_name)->toBe('Adjusted Name');
    expect($suggestion->fresh()->status)->toBe('merged_with_changes');
});

test('an editor can reject a suggestion, leaving the page unchanged', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'Original']);
    app(PageEditorService::class)->grantOwner($person, $editor);

    $reader = User::factory()->withTwoFactor()->create();
    $suggestion = app(SuggestionService::class)->submit($person, $reader, ['first_name' => 'Rejected Name']);

    Livewire::actingAs($editor)
        ->test('pages::people.suggestions', ['person' => $person])
        ->call('reject', $suggestion->id);

    expect($person->fresh()->first_name)->toBe('Original');
    expect($suggestion->fresh()->status)->toBe('rejected');
});

test('rolling back to a prior revision restores it as a new revision on top', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create(['first_name' => 'V1']);
    app(PageEditorService::class)->grantOwner($person, $editor);

    Livewire::actingAs($editor)->test('pages::people.edit', ['person' => $person])
        ->set('first_name', 'V2')->call('save');
    Livewire::actingAs($editor)->test('pages::people.edit', ['person' => $person])
        ->set('first_name', 'V3')->call('save');

    expect($person->fresh()->first_name)->toBe('V3');
    expect($person->revisions()->count())->toBe(2);

    $v2Revision = $person->revisions()->reorder()->oldest('created_at')->first();

    Livewire::actingAs($editor)
        ->test('pages::people.history', ['person' => $person])
        ->call('rollback', $v2Revision->id);

    expect($person->fresh()->first_name)->toBe('V2');
    expect($person->revisions()->count())->toBe(3);
});

test('the last remaining editor of a page cannot be removed', function () {
    $editor = User::factory()->create();
    $person = Person::factory()->create();
    $service = app(PageEditorService::class);
    $service->grantOwner($person, $editor);

    expect(fn () => $service->removeEditor($person, $editor))->toThrow(RuntimeException::class);
});
