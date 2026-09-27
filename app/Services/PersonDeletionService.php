<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class PersonDeletionService
{
    /**
     * Permanently removes a person and everything scoped to their page.
     * Relationships and story tags cascade at the database level; page
     * editors, revision history, and suggestions don't (they're polymorphic,
     * with no foreign key to cascade on), so those are cleared explicitly
     * here. A linked user account is preserved but unlinked — deleting a
     * person only nulls out that user's person_id, it never deletes the
     * account itself.
     */
    public function delete(Person $person): void
    {
        DB::transaction(function () use ($person) {
            $person->pageEditors()->delete();
            $person->revisions()->delete();
            $person->suggestions()->delete();
            $person->delete();
        });
    }
}
