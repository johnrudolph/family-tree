<?php

namespace App\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SlackNotifier
{
    /**
     * Sends a notification to the family's Slack channel, via the single
     * incoming webhook URL configured for this instance. A no-op when
     * none is configured, so Slack posting stays entirely optional.
     */
    public function send(Notification $notification): void
    {
        if (! $webhookUrl = config('services.slack.webhook_url')) {
            return;
        }

        NotificationFacade::route('slack', $webhookUrl)->notify($notification);
    }
}
