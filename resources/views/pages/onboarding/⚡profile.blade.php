<?php

use App\Models\Person;
use App\Services\RevisionService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.auth')] #[Title('Your profile')] class extends Component {
    use WithFileUploads;

    public Person $person;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $preferred_name = '';

    public bool $use_preferred_name_everywhere = false;

    public ?string $dob = null;

    public string $contact_email = '';

    public string $phone = '';

    public string $address = '';

    /** @var array<int, string> */
    public array $social_links = [];

    public string $newSocialLink = '';

    public $photo = null;

    public function mount(): void
    {
        $person = Auth::user()->person;
        abort_unless($person, 404);

        $this->person = $person;
        $this->first_name = $person->first_name;
        $this->middle_name = $person->middle_name ?? '';
        $this->last_name = $person->last_name ?? '';
        $this->preferred_name = $person->preferred_name ?? '';
        $this->use_preferred_name_everywhere = $person->use_preferred_name_everywhere;
        $this->dob = $person->dob?->toDateString();
        $this->contact_email = $person->contact_email ?? '';
        $this->phone = $person->phone ?? '';
        $this->address = $person->address ?? '';
        $this->social_links = $person->social_links ?? [];
    }

    public function addSocialLink(): void
    {
        $validated = $this->validate(['newSocialLink' => ['required', 'url', 'max:255']])['newSocialLink'];

        $this->social_links[] = $validated;
        $this->newSocialLink = '';
    }

    public function removeSocialLink(int $index): void
    {
        unset($this->social_links[$index]);
        $this->social_links = array_values($this->social_links);
    }

    public function save(): void
    {
        Gate::authorize('update', $this->person);
        Gate::authorize('manageEnrichment', $this->person);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'use_preferred_name_everywhere' => ['boolean'],
            'dob' => ['nullable', 'date'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $coreData = [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'preferred_name' => $validated['preferred_name'] ?: null,
            'use_preferred_name_everywhere' => $validated['preferred_name'] && $validated['use_preferred_name_everywhere'],
            'dob' => $validated['dob'] ?: null,
        ];

        $this->person->update([
            ...$coreData,
            'contact_email' => $validated['contact_email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'] ?: null,
            'social_links' => $this->social_links ?: null,
            'consented_at' => $this->person->consented_at ?? now(),
        ]);

        if ($this->photo) {
            $this->person->addMedia($this->photo->getRealPath())
                ->usingFileName($this->photo->getClientOriginalName())
                ->toMediaCollection('photo');
        }

        app(RevisionService::class)->record($this->person, Auth::user(), $coreData);

        Flux::toast(variant: 'success', text: __('Saved.'));

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function skip(): void
    {
        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col items-center gap-2 text-center">
        <flux:icon.user-circle class="size-10 text-blue-600 dark:text-blue-400" />
        <flux:heading size="lg">{{ __('Your profile') }}</flux:heading>
        <flux:text>
            {{ __('Take a moment to share and verify your info — you can always come back and change this later.') }}
        </flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('About you') }}</flux:heading>

            <div class="mt-4 flex flex-col gap-4">
                <flux:input wire:model="first_name" :label="__('First name')" required />
                <flux:input wire:model="middle_name" :label="__('Middle name')" />
                <flux:input wire:model="last_name" :label="__('Last name')" />
                <flux:input wire:model.live="preferred_name" :label="__('Goes by')" />
                @if ($preferred_name !== '')
                    <flux:checkbox wire:model="use_preferred_name_everywhere" :label="__('Use this everywhere')" :description="__('Show \':name\' instead of the full name on the tree, dropdowns, and pages.', ['name' => $preferred_name])" />
                @endif
                <flux:input wire:model="dob" type="date" :label="__('Date of birth')" />
            </div>
        </section>

        <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Photo & contact info') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Your profile picture and contact information are optional, and will be visible to all family members.') }}
            </flux:text>

            <div class="mt-4 flex flex-col gap-4">
                <div class="flex items-center gap-4">
                    <x-person-avatar :person="$person" size="xl" />
                    <div class="flex-1">
                        <flux:input type="file" wire:model="photo" accept="image/*" :label="__('Photo')" />
                        @if ($photo)
                            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('New photo selected — save to apply.') }}</flux:text>
                        @endif
                    </div>
                </div>

                <flux:input wire:model="contact_email" type="email" :label="__('Contact email')" />
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="address" :label="__('Address')" />

                <div>
                    <flux:text class="font-medium">{{ __('Social links') }}</flux:text>
                    <div class="mt-2 space-y-2">
                        @foreach ($social_links as $index => $link)
                            <div class="flex items-center gap-2">
                                <flux:text class="flex-1 truncate text-sm">{{ $link }}</flux:text>
                                <flux:button wire:click="removeSocialLink({{ $index }})" size="sm" variant="ghost" icon="x-mark" />
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-2">
                        <flux:input wire:model="newSocialLink" placeholder="https://…" class="flex-1" />
                        <flux:button wire:click="addSocialLink" type="button" size="sm">{{ __('Add') }}</flux:button>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button wire:click="skip" type="button" variant="ghost">{{ __('Skip for now') }}</flux:button>
        </div>
    </form>
</div>
