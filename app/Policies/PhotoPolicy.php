<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;

class PhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Photo $photo): bool
    {
        return true;
    }

    /**
     * Uploading is admin-only, unlike stories where any member can post.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Photo $photo): bool
    {
        return $user->is_admin || $photo->uploaded_by === $user->id;
    }

    public function delete(User $user, Photo $photo): bool
    {
        return $user->is_admin || $photo->uploaded_by === $user->id;
    }
}
