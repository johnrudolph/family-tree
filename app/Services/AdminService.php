<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class AdminService
{
    public function promote(User $user): void
    {
        $user->update(['is_admin' => true]);
    }

    /**
     * @throws RuntimeException if targeting the hardcoded super admin
     */
    public function demote(User $user): void
    {
        if ($user->isSuperAdmin()) {
            throw new RuntimeException('The super admin can\'t be demoted.');
        }

        $user->update(['is_admin' => false]);
    }
}
