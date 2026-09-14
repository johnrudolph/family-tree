<?php

namespace App\Services;

use App\Models\PageEditor;
use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use RuntimeException;

class PageEditorService
{
    public function grantOwner(Person|Story $page, User $user): PageEditor
    {
        return $page->pageEditors()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => 'owner'],
        );
    }

    public function addCoEditor(Person|Story $page, User $user): PageEditor
    {
        return $page->pageEditors()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => 'co_editor'],
        );
    }

    /**
     * @throws RuntimeException if this would leave the page with no editors
     */
    public function removeEditor(Person|Story $page, User $user): void
    {
        if ($page->pageEditors()->count() <= 1) {
            throw new RuntimeException('A page must always have at least one editor.');
        }

        $page->pageEditors()->where('user_id', $user->id)->delete();
    }
}
