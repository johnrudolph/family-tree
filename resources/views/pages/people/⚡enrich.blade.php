<?php

use App\Models\Person;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My details')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Person $person;

    public string $address = '';

    public string $phone = '';

    public string $contact_email = '';

    /** @var array<int, string> */
    public array $social_links = [];

    public string $newSocialLink = '';

    public $photo = null;

    public function mount(Person $person): void
    {
        Gate::authorize('manageEnrichment', $person);

        $this->person = $person;
        $this->address = $person->address ?? '';
        $this->phone = $person->phone ?? '';
        $this->contact_email = $person->contact_email ?? '';
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
        Gate::authorize('manageEnrichment', $this->person);

        $validated = $this->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->person->update([
            'address' => $validated['address'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'contact_email' => $validated['contact_email'] ?: null,
            'social_links' => $this->social_links ?: null,
            'consented_at' => $this->person->consented_at ?? now(),
        ]);

        if ($this->photo) {
            $this->person->addMedia($this->photo->getRealPath())
                ->usingFileName($this->photo->getClientOriginalName())
                ->toMediaCollection('photo');
        }

        Flux::toast(variant: 'success', text: __('Saved. This information is only ever editable by you.'));

        $this->redirect(route('people.show', $this->person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('My details') }}</flux:heading>
    <flux:subheading>
        {{ __('This information is optional, self-managed, and only ever writable by you — no one else can add or suggest it for you.') }}
    </flux:subheading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
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

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button :href="route('people.show', $person)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
