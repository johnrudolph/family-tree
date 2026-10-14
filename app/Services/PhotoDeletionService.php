<?php

namespace App\Services;

use App\Models\Photo;

class PhotoDeletionService
{
    /**
     * Permanently removes a photo and its underlying file. Its tags to
     * people and links to stories cascade at the database level; any
     * avatar_photo_id/featured_photo_id pointing at it get nulled the same
     * way. Media Library's own delete hook removes the file from disk,
     * since Photo is still the direct owner of its one file.
     */
    public function delete(Photo $photo): void
    {
        $photo->delete();
    }
}
