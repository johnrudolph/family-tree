<?php

use App\Support\MediaUrl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

test('falls back to a plain URL when the disk does not support temporary URLs', function () {
    // The local "public" disk used in dev doesn't support signed temporary
    // URLs and throws — MediaUrl must catch that and fall back, never break
    // image display just because the environment isn't S3/R2.
    $media = Mockery::mock(Media::class);
    $media->shouldReceive('getTemporaryUrl')->once()->andThrow(new RuntimeException('This driver does not support creating temporary URLs.'));
    $media->shouldReceive('getUrl')->once()->andReturn('https://example.com/fallback.jpg');

    expect(MediaUrl::of($media))->toBe('https://example.com/fallback.jpg');
});

test('uses the temporary URL when the disk supports it', function () {
    $media = Mockery::mock(Media::class);
    $media->shouldReceive('getTemporaryUrl')->once()->andReturn('https://example.com/signed.jpg?expires=123');

    expect(MediaUrl::of($media))->toBe('https://example.com/signed.jpg?expires=123');
});
