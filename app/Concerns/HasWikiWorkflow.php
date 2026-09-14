<?php

namespace App\Concerns;

use App\Models\PageEditor;
use App\Models\Revision;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWikiWorkflow
{
    /**
     * @return MorphMany<PageEditor, $this>
     */
    public function pageEditors(): MorphMany
    {
        return $this->morphMany(PageEditor::class, 'editable');
    }

    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisable')->latest('created_at');
    }

    /**
     * @return MorphMany<Suggestion, $this>
     */
    public function suggestions(): MorphMany
    {
        return $this->morphMany(Suggestion::class, 'suggestable');
    }

    /**
     * @return MorphMany<Suggestion, $this>
     */
    public function pendingSuggestions(): MorphMany
    {
        return $this->suggestions()->where('status', 'pending')->latest();
    }

    public function isEditor(User $user): bool
    {
        return $this->pageEditors()->where('user_id', $user->id)->exists();
    }
}
