<?php

namespace App\Notifications;

use App\Models\Story;
use App\Support\StoryBodyParser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoryAdded extends Notification
{
    use Queueable;

    public function __construct(private readonly Story $story)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->story->creator->name} added a story: {$this->story->title}")
            ->view('emails.story-added', [
                'story' => $this->story,
                'bodyHtml' => StoryBodyParser::render($this->story->body ?? '', forEmail: true),
            ]);
    }
}
