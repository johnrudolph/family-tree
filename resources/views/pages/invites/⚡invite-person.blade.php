<?php

use App\Models\Invite;
use App\Models\Person;
use App\Notifications\PersonInvited;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invite a member')] class extends Component {
    public ?int $existingPersonId = null;

    public string $email = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
    }

    /**
     * Only living people with no linked account yet — you can't have an
     * account before you exist on the tree, and someone already accounted
     * for doesn't need a second invite.
     */
    #[Computed]
    public function invitablePeople()
    {
        return Person::query()
            ->where('is_living', true)
            ->whereDoesntHave('user')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function pendingInvites()
    {
        return Invite::query()
            ->with('person')
            ->whereNull('accepted_at')
            ->latest()
            ->get();
    }

    public function sendInvite(): void
    {
        abort_unless(Auth::user()->is_admin, 403);

        $validated = $this->validate([
            'existingPersonId' => ['required', 'exists:people,id'],
            'email' => ['required', 'email'],
        ]);

        $person = Person::findOrFail($validated['existingPersonId']);
        abort_if($person->hasAccount(), 422);
        abort_unless($person->is_living, 422);

        $invite = Invite::create([
            'person_id' => $person->id,
            'email' => $validated['email'],
            'token' => Str::random(64),
            'invited_by' => Auth::id(),
            'expires_at' => now()->addDays(14),
        ]);

        Notification::route('mail', $invite->email)->notify(new PersonInvited($invite));

        $this->reset(['existingPersonId', 'email']);
        unset($this->invitablePeople, $this->pendingInvites);

        Flux::toast(variant: 'success', text: "Invite sent to {$invite->email}.");
    }
}; ?>

<section class="w-full">
    <flux:heading level="1">{{ __('Invite a family member') }}</flux:heading>
    <flux:subheading>{{ __('Every account is tied to a specific person already on the tree — pick who you\'re inviting, then send it to their email.') }}</flux:subheading>

    <form wire:submit="sendInvite" class="my-6 flex max-w-lg flex-col gap-6">
        <flux:select variant="combobox" wire:model="existingPersonId" :label="__('Person')" :placeholder="__('Search people…')" clearable>
            @foreach ($this->invitablePeople as $person)
                <flux:select.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="email" type="email" :label="__('Their email address')" required />

        <div>
            <flux:button type="submit" variant="primary">{{ __('Send invite') }}</flux:button>
        </div>
    </form>

    <flux:separator class="my-8" />

    <flux:heading level="2">{{ __('Pending invites') }}</flux:heading>
    <div class="mt-4 flex flex-col gap-2">
        @forelse ($this->pendingInvites as $invite)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div>
                    <flux:text>{{ $invite->person->fullName() }} — {{ $invite->email }}</flux:text>
                    <flux:text class="text-xs text-zinc-500">
                        {{ $invite->isExpired() ? __('Expired') : __('Expires').' '.$invite->expires_at->diffForHumans() }}
                    </flux:text>
                </div>
            </div>
        @empty
            <flux:text class="text-zinc-500">{{ __('No pending invites.') }}</flux:text>
        @endforelse
    </div>
</section>
