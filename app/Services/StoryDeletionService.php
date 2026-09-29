<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\DB;

class StoryDeletionService
{
    /**
     * Permanently removes a story and everything scoped to its page. The
     * tag to people, gallery photos, and audio recording cascade (people
     * via the database, media via Media Library's own delete hook); page
     * editors, revision history, and suggestions don't (they're polymorphic,
     * with no foreign key to cascade on), so those are cleared explicitly
     * here.
     */
    public function delete(Story $story): void
    {
        DB::transaction(function () use ($story) {
            $story->pageEditors()->delete();
            $story->revisions()->delete();
            $story->suggestions()->delete();
            $story->delete();
        });
    }
}
