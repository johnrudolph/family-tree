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
     */
    public static function of(Media $media, ?Carbon $expiration = null): string
    {
        try {
            return $media->getTemporaryUrl($expiration ?? now()->addHour());
        } catch (RuntimeException) {
            return $media->getUrl();
        }
    }
}
