<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Story;
use App\Models\Suggestion;
use App\Models\User;
use App\Notifications\SuggestionReviewed;
use App\Notifications\SuggestionSubmitted;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class SuggestionService
{
    public function __construct(private readonly RevisionService $revisions)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(Person|Story $page, User $proposer, array $payload): Suggestion
    {
        $suggestion = $page->suggestions()->create([
            'user_id' => $proposer->id,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        Notification::send($page->pageEditors()->with('user')->get()->pluck('user'), new SuggestionSubmitted($suggestion));

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>|null  $overridePayload  when set, this is a "merge with changes"
     */
    public function merge(Suggestion $suggestion, User $reviewer, ?array $overridePayload = null): void
    {
        $finalPayload = $overridePayload ?? $suggestion->payload;
        $page = $suggestion->suggestable;

        if (! $page instanceof Person && ! $page instanceof Story) {
            throw new RuntimeException('Suggestion is not attached to a valid wiki page.');
        }

        $page->update($finalPayload);
        $this->revisions->record($page, $reviewer, $finalPayload);

        $suggestion->update([
            'status' => $overridePayload !== null ? 'merged_with_changes' : 'merged',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $suggestion->user->notify(new SuggestionReviewed($suggestion));
    }

    public function reject(Suggestion $suggestion, User $reviewer, ?string $note = null): void
    {
        $suggestion->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $suggestion->user->notify(new SuggestionReviewed($suggestion));
    }
}
