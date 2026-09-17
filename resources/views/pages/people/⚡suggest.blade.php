<?php

use App\Models\Person;
use App\Services\SuggestionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Suggest an edit')] class extends Component {
    #[Locked]
    public Person $person;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $preferred_name = '';

    public bool $use_preferred_name_everywhere = false;

    public ?string $dob = null;

    public ?string $birth_city = null;

    public ?string $dod = null;

    public ?string $death_city = null;

    public bool $is_living = true;

    public string $bio = '';

    public function mount(Person $person): void
    {
        Gate::authorize('suggest', $person);

        $this->person = $person;
        $this->first_name = $person->first_name;
        $this->middle_name = $person->middle_name ?? '';
        $this->last_name = $person->last_name ?? '';
        $this->preferred_name = $person->preferred_name ?? '';
        $this->use_preferred_name_everywhere = $person->use_preferred_name_everywhere;
        $this->dob = $person->dob?->toDateString();
        $this->birth_city = $person->birth_city;
        $this->dod = $person->dod?->toDateString();
        $this->death_city = $person->death_city;
        $this->is_living = $person->is_living;
        $this->bio = $person->bio ?? '';
    }

    public function submit(): void
    {
        Gate::authorize('suggest', $this->person);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'use_preferred_name_everywhere' => ['boolean'],
            'dob' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'dod' => ['nullable', 'date'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'is_living' => ['boolean'],
            'bio' => ['nullable', 'string', 'max:20000'],
        ]);

        $payload = [
            ...$validated,
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'preferred_name' => $validated['preferred_name'] ?: null,
            'use_preferred_name_everywhere' => $validated['preferred_name'] && $validated['use_preferred_name_everywhere'],
        ];

        app(SuggestionService::class)->submit($this->person, Auth::user(), $payload);

        $this->redirect(route('people.show', $this->person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Suggest an edit') }}</flux:heading>
    <flux:subheading>
        {{ __('Your changes go to this page\'s editors for review — they can merge, adjust, or decline them.') }}
    </flux:subheading>

    <form wire:submit="submit" class="mt-6 flex flex-col gap-6">
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
        <flux:input wire:model="birth_city" :label="__('Birth city')" />
        <flux:checkbox wire:model="is_living" :label="__('Living')" />
        <div x-show="! $wire.is_living" class="flex flex-col gap-6">
            <flux:input wire:model="dod" type="date" :label="__('Date of death')" />
            <flux:input wire:model="death_city" :label="__('Death city')" />
        </div>
        <flux:editor wire:model="bio" :label="__('Bio')" toolbar="heading | bold italic underline strike | bullet ordered blockquote | link" class="**:data-[slot=content]:min-h-56" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Submit suggestion') }}</flux:button>
            <flux:button :href="route('people.show', $person)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
