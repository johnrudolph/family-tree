<?php

use App\Models\Person;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a member can view the photos index', function () {
    $viewer = User::factory()->withTwoFactor()->create();

    $this->actingAs($viewer)
        ->get(route('photos.index'))
        ->assertOk()
        ->assertSee('Photos');
});

test('a guest cannot view the photos index', function () {
    $this->get(route('photos.index'))->assertRedirect(route('login'));
});

test('an admin can upload a photo from the library', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::photos.index')
        ->set('newPhotos', [UploadedFile::fake()->image('a.jpg')])
        ->assertHasNoErrors();

    expect(Photo::count())->toBe(1);
});

test('a non-admin cannot upload a photo from the library', function () {
    $member = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($member)
        ->test('pages::photos.index')
        ->set('newPhotos', [UploadedFile::fake()->image('a.jpg')])
        ->assertForbidden();

    expect(Photo::count())->toBe(0);
});

test('uploading several photos at once creates one each, skipping any invalid file without losing the rest', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::photos.index')
        ->set('newPhotos', [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->create('not-an-image.txt', 10),
            UploadedFile::fake()->image('b.jpg'),
        ]);

    expect(Photo::count())->toBe(2);
});

test('a member can view a photo\'s page', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $photo = Photo::factory()->create(['description' => 'A family reunion']);

    $this->actingAs($viewer)
        ->get(route('photos.show', $photo))
        ->assertOk()
        ->assertSee('A family reunion');
});

test('the uploader can edit their photo\'s description, date, and tagged people', function () {
    $uploader = User::factory()->withTwoFactor()->create();
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);
    $alice = Person::factory()->create();

    Livewire::actingAs($uploader)
        ->test('pages::photos.show', ['photo' => $photo])
        ->set('description', 'Updated description')
        ->set('hasDate', true)
        ->set('date_precision', 'year')
        ->set('year', '1980')
        ->set('person_ids', [$alice->id])
        ->call('save')
        ->assertHasNoErrors();

    $photo->refresh();
    expect($photo->description)->toBe('Updated description');
    expect($photo->date->format('Y'))->toBe('1980');
    expect($photo->date_precision)->toBe('year');
    expect($photo->people()->pluck('people.id')->all())->toBe([$alice->id]);
});

test('the uploader can place and remove a pin for a tagged person', function () {
    $uploader = User::factory()->withTwoFactor()->create();
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);
    $alice = Person::factory()->create();
    $photo->people()->attach($alice->id);

    Livewire::actingAs($uploader)
        ->test('pages::photos.show', ['photo' => $photo])
        ->call('togglePlacingPin', $alice->id)
        ->call('placePin', 33.3, 66.6);

    $pivot = $photo->people()->find($alice->id)->pivot;
    expect((float) $pivot->x)->toBe(33.3);
    expect((float) $pivot->y)->toBe(66.6);

    Livewire::actingAs($uploader)
        ->test('pages::photos.show', ['photo' => $photo])
        ->call('clearPersonPin', $alice->id);

    expect($photo->people()->find($alice->id)->pivot->x)->toBeNull();
});

test('a stranger cannot edit someone else\'s photo', function () {
    $uploader = User::factory()->create();
    $stranger = User::factory()->withTwoFactor()->create();
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);

    Livewire::actingAs($stranger)
        ->test('pages::photos.show', ['photo' => $photo])
        ->set('description', 'Hijacked')
        ->call('save')
        ->assertForbidden();
});

test('the uploader can delete their own photo', function () {
    $uploader = User::factory()->withTwoFactor()->create();
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);

    Livewire::actingAs($uploader)
        ->test('pages::photos.show', ['photo' => $photo])
        ->call('deletePhoto')
        ->assertHasNoErrors();

    expect(Photo::find($photo->id))->toBeNull();
});

test('a stranger cannot delete someone else\'s photo', function () {
    $uploader = User::factory()->create();
    $stranger = User::factory()->withTwoFactor()->create();
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);

    Livewire::actingAs($stranger)
        ->test('pages::photos.show', ['photo' => $photo])
        ->call('deletePhoto')
        ->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
});
