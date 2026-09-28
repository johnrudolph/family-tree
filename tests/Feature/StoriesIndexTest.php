<?php

use App\Models\Location;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a member can view the stories index', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    Story::factory()->create(['title' => 'A Summer Trip']);

    $this->actingAs($viewer)
        ->get(route('stories.index'))
        ->assertOk()
        ->assertSee('A Summer Trip');
});

test('the stories index shows a story\'s location and featured image', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    $location = Location::factory()->create(['city' => 'Portland', 'region' => 'OR']);
    $story = Story::factory()->create(['title' => 'A Trip to the Coast', 'location_id' => $location->id]);
    $media = $story->addMediaFromString('fake-image-bytes')
        ->usingFileName('a.jpg')
        ->preservingOriginal()
        ->toMediaCollection('gallery');
    $story->featureImage($media);

    $response = $this->actingAs($viewer)->get(route('stories.index'));

    $response->assertOk()
        ->assertSee('Portland, OR')
        ->assertSee('a.jpg', false);
});

test('a story with no photos shows a placeholder instead of a broken image', function () {
    $viewer = User::factory()->withTwoFactor()->create();
    Story::factory()->create(['title' => 'No Photos Here']);

    $this->actingAs($viewer)
        ->get(route('stories.index'))
        ->assertOk()
        ->assertSee('No Photos Here');
});

test('a guest cannot view the stories index', function () {
    $this->get(route('stories.index'))->assertRedirect(route('login'));
});
