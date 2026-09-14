<?php

use App\Concerns\PasswordValidationRules;
use App\Models\Invite;
use App\Models\User;
use App\Services\PageEditorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Accept your invite')] class extends Component {
    use PasswordValidationRules;

    #[Locked]
    public string $token = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?Invite $invite = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->invite = Invite::query()
            ->with('person')
            ->where('token', $token)
            ->first();

        if ($this->invite && ! $this->invite->isAccepted() && ! $this->invite->isExpired()) {
            $this->name = $this->invite->person->fullName();
        }
    }

    public function accept(): void
    {
        abort_if(! $this->invite || $this->invite->isAccepted() || $this->invite->isExpired(), 404);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $this->invite->email,
                'password' => $validated['password'],
                'email_verified_at' => now(),
                'person_id' => $this->invite->person_id,
            ]);

            $this->invite->accepted_at = now();
            $this->invite->save();

            app(PageEditorService::class)->grantOwner($this->invite->person, $user);

            return $user;
        });

        Auth::login($user);

        $this->redirect(route('onboarding.security'), navigate: true);
    }
}; ?>

<div>
    @if (! $invite || $invite->isAccepted() || $invite->isExpired())
        <flux:heading level="1">{{ __('This invite is no longer valid') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Ask whoever invited you to send a new one.') }}</flux:text>
    @else
        <flux:heading level="1">{{ __('Welcome to the family tree') }}</flux:heading>
        <flux:subheading>{{ __('You\'re joining as').' '.$invite->person->fullName() }}</flux:subheading>

        <form wire:submit="accept" class="mt-6 flex flex-col gap-6">
            <flux:input wire:model="name" :label="__('Your name')" required />
            <flux:input wire:model="password" type="password" :label="__('Password')" required viewable
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />
            <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" required viewable />

            <flux:button type="submit" variant="primary">{{ __('Create my account') }}</flux:button>
        </form>

        <flux:text class="mt-4 text-xs text-zinc-500">
            {{ __('Two-factor authentication is required for every account — you\'ll set it up next.') }}
        </flux:text>
    @endif
</div>
