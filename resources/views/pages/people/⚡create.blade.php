<?php

use App\Models\Person;
use App\Services\PageEditorService;
use App\Support\PersonDateInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add a person')] class extends Component {
    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $preferred_name = '';

    public bool $use_preferred_name_everywhere = false;

    public ?string $dob = null;

    public string $dob_precision = 'exact';

    public ?string $dob_year = null;

    public ?int $birth_location_id = null;

    public ?string $dod = null;

    public string $dod_precision = 'exact';

    public ?string $dod_year = null;

    public ?int $death_location_id = null;

    public bool $is_living = true;

    public function mount(): void
    {
        Gate::authorize('create', Person::class);
    }

    #[On('location-selected')]
    public function onLocationSelected(string $field, ?int $locationId): void
    {
        match ($field) {
            'birth' => $this->birth_location_id = $locationId,
            'death' => $this->death_location_id = $locationId,
            default => null,
        };
    }

    public function save(): void
    {
        Gate::authorize('create', Person::class);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'use_preferred_name_everywhere' => ['boolean'],
            'dob_precision' => ['required', 'in:exact,year'],
            'dob' => ['nullable', 'date'],
            'dob_year' => ['nullable', 'integer', 'min:1000', 'max:'.now()->year],
            'birth_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'dod_precision' => ['required', 'in:exact,year'],
            'dod' => ['nullable', 'date'],
            'dod_year' => ['nullable', 'integer', 'min:1000', 'max:'.now()->year],
            'death_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'is_living' => ['boolean'],
        ]);

        [$dob, $dobPrecision] = PersonDateInput::resolve($validated['dob_precision'], $validated['dob'], $validated['dob_year']);
        [$dod, $dodPrecision] = PersonDateInput::resolve($validated['dod_precision'], $validated['dod'], $validated['dod_year']);

        $person = Person::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'preferred_name' => $validated['preferred_name'] ?: null,
            'use_preferred_name_everywhere' => $validated['preferred_name'] && $validated['use_preferred_name_everywhere'],
            'dob' => $dob,
            'dob_precision' => $dobPrecision,
            'birth_location_id' => $validated['birth_location_id'],
            'dod' => $dod,
            'dod_precision' => $dodPrecision,
            'death_location_id' => $validated['death_location_id'],
            'is_living' => $validated['is_living'],
            'created_by' => Auth::id(),
        ]);

        app(PageEditorService::class)->grantOwner($person, Auth::user());

        $this->redirect(route('people.show', $person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Add a person') }}</flux:heading>
    <flux:subheading>
        {{ __('For relatives who aren\'t on the tree yet — they don\'t need an account or email to be added. Invite them later from their page if they want to join.') }}
    </flux:subheading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:heading level="2" size="sm">{{ __('Name on birth certificate') }}</flux:heading>
        <flux:input wire:model="first_name" :label="__('First name')" required autofocus />
        <flux:input wire:model="middle_name" :label="__('Middle name')" />
        <flux:input wire:model="last_name" :label="__('Last name')" />

        <flux:separator />

        <flux:input wire:model="preferred_name" :label="__('Goes by')" />
        <div x-show="$wire.preferred_name !== ''">
            <flux:checkbox wire:model="use_preferred_name_everywhere" :label="__('Use this everywhere')" :description="__('Show \':name\' instead of the full name on the tree, dropdowns, and pages.', ['name' => $preferred_name])" />
        </div>

        <flux:separator />

        <x-date-precision-field label="{{ __('Date of birth') }}" date-model="dob" precision-model="dob_precision" year-model="dob_year" />
        <livewire:location-picker field="birth" :location-id="$birth_location_id" :label="__('Birth location')" wire:key="birth-location-picker" />
        <flux:checkbox wire:model="is_living" :label="__('Living')" />
        <div x-show="! $wire.is_living" class="flex flex-col gap-6">
            <x-date-precision-field label="{{ __('Date of death') }}" date-model="dod" precision-model="dod_precision" year-model="dod_year" />
            <livewire:location-picker field="death" :location-id="$death_location_id" :label="__('Death location')" wire:key="death-location-picker" />
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Add person') }}</flux:button>
            <flux:button :href="route('people.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
