<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

/**
 * A single, generic "post this to the family Slack channel" notification —
 * reused for every kind of activity announcement (new stories, and whatever
 * other media types get added later) so each one doesn't need its own
 * notification class.
 */
class SlackAnnouncement extends Notification
{
    use Queueable;

    public function __construct(private readonly string $text)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)->text($this->text);
    }
}
