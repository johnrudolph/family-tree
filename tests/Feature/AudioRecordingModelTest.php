<?php

use App\Models\AudioRecording;
use App\Models\User;

test('only an admin can create an audio recording', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $member = User::factory()->create(['is_admin' => false]);

    expect($admin->can('create', AudioRecording::class))->toBeTrue();
    expect($member->can('create', AudioRecording::class))->toBeFalse();
});

test('the uploader or an admin can update or delete their recording, but no one else can', function () {
    $uploader = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);
    $stranger = User::factory()->create(['is_admin' => false]);
    $recording = AudioRecording::factory()->create(['uploaded_by' => $uploader->id]);

    expect($uploader->can('update', $recording))->toBeTrue();
    expect($admin->can('update', $recording))->toBeTrue();
    expect($stranger->can('update', $recording))->toBeFalse();

    expect($uploader->can('delete', $recording))->toBeTrue();
    expect($stranger->can('delete', $recording))->toBeFalse();
});
