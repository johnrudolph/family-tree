<?php

namespace App\Notifications;

use App\Models\Person;
use App\Models\Story;
use App\Models\Suggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

class SuggestionSubmitted extends Notification
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

        return (new MailMessage)
            ->subject("New suggestion for \"{$page->wikiTitle()}\"")
            ->line("{$this->suggestion->user->name} suggested a change to \"{$page->wikiTitle()}\".")
            ->action('Review suggestion', $page->wikiSuggestionsUrl());
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
            'url' => $page->wikiSuggestionsUrl(),
            'proposer' => $this->suggestion->user->name,
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
