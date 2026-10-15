<?php

use App\Models\ApiToken;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->token = ApiToken::issue('test');
});

test('listing media returns a signed url for each file', function () {
    $photo = Photo::create();
    $photo->addMediaFromString('fake-image-bytes')->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('file');

    $response = $this->withToken($this->token)->getJson('/api/v1/media')->assertOk();

    expect($response->json('data.0.file_name'))->toBe('a.jpg');
    expect($response->json('data.0.url'))->toBeString();
});

test('media can be filtered by model_type and collection_name', function () {
    $photo = Photo::create();
    $photo->addMediaFromString('fake-image-bytes')->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('file');

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/media?'.http_build_query(['model_type' => 'App\\Models\\Photo', 'collection_name' => 'file']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/media?'.http_build_query(['model_type' => 'App\\Models\\Nothing']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(0);
});

test('showing a single media record returns it', function () {
    $photo = Photo::create();
    $media = $photo->addMediaFromString('fake-image-bytes')->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('file');

    $this->withToken($this->token)
        ->getJson("/api/v1/media/{$media->id}")
        ->assertOk()
        ->assertJsonPath('file_name', 'a.jpg');
});

test('a missing media record 404s', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/media/999')
        ->assertNotFound();
});
