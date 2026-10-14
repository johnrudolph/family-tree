<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\DB;

class StoryDeletionService
{
    /**
     * Permanently removes a story and everything scoped to its page. The
     * tag to people and the links to its photos/audio cascade at the
     * database level — photos and audio recordings are never owned by a
     * story, only referenced, so they survive and stay usable elsewhere.
     * Page editors, revision history, and suggestions don't cascade (they're
     * polymorphic, with no foreign key to cascade on), so those are cleared
     * explicitly here.
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
