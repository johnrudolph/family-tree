<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Revision;
use App\Models\Story;
use App\Models\User;

class RevisionService
{
    /**
     * Snapshot the given fields as a new revision after they've been applied to the page.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Person|Story $page, User $user, array $data): Revision
    {
        return $page->revisions()->create([
            'user_id' => $user->id,
            'data' => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Restore a prior revision's snapshot by applying it and recording a new revision
     * on top — history is never rewritten, only added to.
     */
    public function rollback(Person|Story $page, Revision $revision, User $user): void
    {
        $page->update($revision->data);

        $this->record($page, $user, $revision->data);
    }
}
