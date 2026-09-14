<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Story $story): bool
    {
        return true;
    }

    /**
     * Any member can start a story page — the invite-only membership is the trust boundary.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Story $story): bool
    {
        return $user->is_admin || $story->isEditor($user);
    }

    public function suggest(User $user, Story $story): bool
    {
        return ! $story->isEditor($user);
    }

    public function delete(User $user, Story $story): bool
    {
        return $user->is_admin || $story->isEditor($user);
    }
}
