<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaUrl
{
    /**
     * A short-lived signed URL where the storage disk supports it (S3/R2 in
     * production — this app is invite-only and photos shouldn't be
     * reachable by anyone who merely guesses or leaks a URL). Falls back to
     * a plain URL on disks that don't support temporary URLs, e.g. the
     * local "public" disk used in dev, where `getTemporaryUrl()` throws.
     *
     * Pass `$conversion` (e.g. "thumb") wherever a small image is actually
     * what's needed — an avatar, a grid card — rather than the original.
     * Falls back to the original if that conversion doesn't exist for this
     * media (e.g. non-image files, or a conversion that failed to generate)
     * rather than linking to a file that was never created.
     */
    public static function of(Media $media, ?Carbon $expiration = null, ?string $conversion = null): string
    {
        if ($conversion && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = null;
        }

        try {
            return $media->getTemporaryUrl($expiration ?? now()->addHour(), $conversion ?? '');
        } catch (RuntimeException) {
            return $conversion ? $media->getUrl($conversion) : $media->getUrl();
        }
    }
}
