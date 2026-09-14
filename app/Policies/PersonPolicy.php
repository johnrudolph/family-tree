<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    /**
     * Any authenticated member of the tree may view any person's core page —
     * the invite-only auth wall is the privacy boundary, not per-record visibility.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Person $person): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Core facts (name/dob/relationships/bio) — direct edit rights belong to this
     * page's editors. Admins can still step in for moderation.
     */
    public function update(User $user, Person $person): bool
    {
        return $user->is_admin || $person->isEditor($user);
    }

    /**
     * Non-editors can propose changes instead of editing directly — editors just edit.
     */
    public function suggest(User $user, Person $person): bool
    {
        return ! $person->isEditor($user);
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->is_admin;
    }

    /**
     * Self-enrichment fields (address, phone, contact email, social links, photo).
     * For the living, only the person themself may set these — nobody may add this
     * data on someone else's behalf. For the deceased, there's no one to withhold
     * consent, so this page's editors may add memorial photos/details instead.
     */
    public function manageEnrichment(User $user, Person $person): bool
    {
        if ($user->person_id !== null && $user->person_id === $person->id) {
            return true;
        }

        return ! $person->is_living && $person->isEditor($user);
    }
}
