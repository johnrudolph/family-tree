<?php

namespace App\Policies;

use App\Models\AudioRecording;
use App\Models\User;

class AudioRecordingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AudioRecording $audioRecording): bool
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

    public function update(User $user, AudioRecording $audioRecording): bool
    {
        return $user->is_admin || $audioRecording->uploaded_by === $user->id;
    }

    public function delete(User $user, AudioRecording $audioRecording): bool
    {
        return $user->is_admin || $audioRecording->uploaded_by === $user->id;
    }
}
