<?php

use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use App\Services\PageEditorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('any member can create a story and tag people in it', function () {
    $user = User::factory()->withTwoFactor()->create();
    $person = Person::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.create')
        ->set('title', 'The Move to Ohio')
        ->set('body', 'It was 1962…')
        ->set('start_date', '1962-06-01')
        ->set('person_ids', [$person->id])
        ->call('save')
        ->assertHasNoErrors();

    $story = Story::query()->where('title', 'The Move to Ohio')->firstOrFail();
    expect($story->people->pluck('id')->all())->toBe([$person->id]);
});

test('creating a story sanitizes the rich text body before saving', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.create')
        ->set('title', 'Tainted Story')
        ->set('body', '<p>hello</p><script>alert(1)</script>')
        ->set('start_date', '2020-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $story = Story::query()->where('title', 'Tainted Story')->firstOrFail();
    expect($story->body)->toBe('<p>hello</p>');
});

test('a story page shows tagged people and renders its rich text body', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create(['title' => 'A Family Tale', 'body' => '<p><strong>bold</strong> text</p>']);

    $this->actingAs($viewer)
        ->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('A Family Tale')
        ->assertSee('<strong>bold</strong>', false);
});

test('a story can be created with a year-only start date and an end date', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.create')
        ->set('title', 'The War Years')
        ->set('start_date_precision', 'year')
        ->set('start_year', '1943')
        ->set('has_end_date', true)
        ->set('end_date_precision', 'year')
        ->set('end_year', '1945')
        ->call('save')
        ->assertHasNoErrors();

    $story = Story::query()->where('title', 'The War Years')->firstOrFail();
    expect($story->start_date->toDateString())->toBe('1943-01-01');
    expect($story->start_date_precision)->toBe('year');
    expect($story->end_date->toDateString())->toBe('1945-01-01');
    expect($story->end_date_precision)->toBe('year');
});

test('a story title over 70 characters is rejected', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.create')
        ->set('title', str_repeat('a', 71))
        ->set('start_date', '2020-01-01')
        ->call('save')
        ->assertHasErrors(['title']);
});

test('an editor can feature a gallery image, and only one image is featured at a time', function () {
    Storage::fake('public');

    $editor = User::factory()->withTwoFactor()->create();
    $story = Story::factory()->create();
    app(PageEditorService::class)->grantOwner($story, $editor);

    Livewire::actingAs($editor)
        ->test('pages::stories.edit', ['story' => $story])
        ->set('newPhotos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    [$first, $second] = $story->fresh()->galleryMedia()->all();

    Livewire::actingAs($editor)
        ->test('pages::stories.edit', ['story' => $story])
        ->call('featureImage', $first->id)
        ->assertHasNoErrors();

    expect($first->fresh()->getCustomProperty('featured'))->toBeTrue();

    Livewire::actingAs($editor)
        ->test('pages::stories.edit', ['story' => $story])
        ->call('featureImage', $second->id)
        ->assertHasNoErrors();

    expect($first->fresh()->getCustomProperty('featured'))->toBeFalsy();
    expect($second->fresh()->getCustomProperty('featured'))->toBeTrue();
    expect($story->fresh()->featuredImage()->id)->toBe($second->id);
});

test('a non-creator, non-admin cannot edit a story', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $story = Story::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::stories.edit', ['story' => $story])
        ->assertForbidden();
});
