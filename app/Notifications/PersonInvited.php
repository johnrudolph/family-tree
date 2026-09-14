<?php

namespace App\Notifications;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonInvited extends Notification
{
    use Queueable;

    public function __construct(private readonly Invite $invite)
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
        $person = $this->invite->person;

        return (new MailMessage)
            ->subject('You\'re invited to the family tree')
            ->greeting("Hi {$person->first_name},")
            ->line('You\'ve been invited to join the private family tree as '.$person->fullName().'.')
            ->action('Accept invite', route('invites.accept', $this->invite->token))
            ->line('This invite expires on '.$this->invite->expires_at?->format('F j, Y').'.');
    }
}
