<?php

namespace App\Notifications;

use App\Models\Person;
use App\Models\Story;
use App\Models\Suggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

class SuggestionReviewed extends Notification
{
    use Queueable;

    public function __construct(private readonly Suggestion $suggestion)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $page = $this->page();
        $verb = match ($this->suggestion->status) {
            'merged' => 'merged your suggestion for',
            'merged_with_changes' => 'merged your suggestion (with some changes) for',
            default => 'declined your suggestion for',
        };

        $message = (new MailMessage)
            ->subject("Your suggestion for \"{$page->wikiTitle()}\" was reviewed")
            ->line("{$this->suggestion->reviewer?->name} {$verb} \"{$page->wikiTitle()}\".");

        if ($this->suggestion->review_note) {
            $message->line("Note: {$this->suggestion->review_note}");
        }

        return $message->action('View page', $page->wikiShowUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $page = $this->page();

        return [
            'suggestion_id' => $this->suggestion->id,
            'page_title' => $page->wikiTitle(),
            'status' => $this->suggestion->status,
            'url' => $page->wikiShowUrl(),
        ];
    }

    private function page(): Person|Story
    {
        $page = $this->suggestion->suggestable;

        if (! $page instanceof Person && ! $page instanceof Story) {
            throw new RuntimeException('Suggestion is not attached to a valid wiki page.');
        }

        return $page;
    }
}
