<?php

use App\Models\Person;
use App\Models\Photo;
use App\Models\Story;
use App\Models\User;

test('a photo can be tagged to multiple people, like a group photo', function () {
    $photo = Photo::factory()->create();
    $alice = Person::factory()->create();
    $bob = Person::factory()->create();

    $photo->people()->attach([$alice->id, $bob->id]);

    expect($photo->people()->pluck('people.id')->all())->toEqualCanonicalizing([$alice->id, $bob->id]);
});

test('a photo can be referenced by multiple stories without being copied', function () {
    $photo = Photo::factory()->create();
    $storyOne = Story::factory()->create();
    $storyTwo = Story::factory()->create();

    $photo->stories()->attach([$storyOne->id, $storyTwo->id]);

    expect($photo->stories()->pluck('stories.id')->all())->toEqualCanonicalizing([$storyOne->id, $storyTwo->id]);
});

test('only an admin can create a photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $member = User::factory()->create(['is_admin' => false]);

    expect($admin->can('create', Photo::class))->toBeTrue();
    expect($member->can('create', Photo::class))->toBeFalse();
});

test('the uploader or an admin can update or delete their photo, but no one else can', function () {
    $uploader = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);
    $stranger = User::factory()->create(['is_admin' => false]);
    $photo = Photo::factory()->create(['uploaded_by' => $uploader->id]);

    expect($uploader->can('update', $photo))->toBeTrue();
    expect($admin->can('update', $photo))->toBeTrue();
    expect($stranger->can('update', $photo))->toBeFalse();

    expect($uploader->can('delete', $photo))->toBeTrue();
    expect($stranger->can('delete', $photo))->toBeFalse();
});
