<?php

use App\Models\Person;
use App\Services\RevisionService;
use App\Support\RichTextSanitizer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit person')] class extends Component {
    #[Locked]
    public Person $person;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $preferred_name = '';

    public bool $use_preferred_name_everywhere = false;

    public ?string $dob = null;

    public ?string $dod = null;

    public bool $is_living = true;

    public string $bio = '';

    public function mount(Person $person): void
    {
        Gate::authorize('update', $person);

        $this->person = $person;
        $this->first_name = $person->first_name;
        $this->middle_name = $person->middle_name ?? '';
        $this->last_name = $person->last_name ?? '';
        $this->preferred_name = $person->preferred_name ?? '';
        $this->use_preferred_name_everywhere = $person->use_preferred_name_everywhere;
        $this->dob = $person->dob?->toDateString();
        $this->dod = $person->dod?->toDateString();
        $this->is_living = $person->is_living;
        $this->bio = $person->bio ?? '';
    }

    public function save(): void
    {
        Gate::authorize('update', $this->person);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'use_preferred_name_everywhere' => ['boolean'],
            'dob' => ['nullable', 'date'],
            'dod' => ['nullable', 'date'],
            'is_living' => ['boolean'],
            'bio' => ['nullable', 'string', 'max:20000'],
        ]);

        $data = [
            ...$validated,
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'preferred_name' => $validated['preferred_name'] ?: null,
            'use_preferred_name_everywhere' => $validated['preferred_name'] && $validated['use_preferred_name_everywhere'],
            'bio' => RichTextSanitizer::clean($validated['bio']),
        ];

        $this->person->update($data);

        app(RevisionService::class)->record($this->person, Auth::user(), $data);

        Flux::toast(variant: 'success', text: __('Saved.'));

        $this->redirect(route('people.show', $this->person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Edit') }} {{ $person->fullName() }}</flux:heading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:heading level="2" size="sm">{{ __('Name on birth certificate') }}</flux:heading>
        <flux:input wire:model="first_name" :label="__('First name')" required />
        <flux:input wire:model="middle_name" :label="__('Middle name')" />
        <flux:input wire:model="last_name" :label="__('Last name')" />

        <flux:separator />

        <flux:input wire:model="preferred_name" :label="__('Goes by')" />
        <div x-show="$wire.preferred_name !== ''">
            <flux:checkbox wire:model="use_preferred_name_everywhere" :label="__('Use this everywhere')" :description="__('Show \':name\' instead of the full name on the tree, dropdowns, and pages.', ['name' => $preferred_name])" />
        </div>

        <flux:separator />

        <flux:input wire:model="dob" type="date" :label="__('Date of birth')" />
        <flux:checkbox wire:model="is_living" :label="__('Living')" />
        <div x-show="! $wire.is_living">
            <flux:input wire:model="dod" type="date" :label="__('Date of death')" />
        </div>
        <flux:editor wire:model="bio" :label="__('Bio')" toolbar="heading | bold italic underline strike | bullet ordered blockquote | link" class="**:data-[slot=content]:min-h-56" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button :href="route('people.show', $person)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
