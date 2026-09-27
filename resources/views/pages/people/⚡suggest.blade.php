<?php

use App\Models\Person;
use App\Services\SuggestionService;
use App\Support\PersonDateInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Suggest an edit')] class extends Component {
    #[Locked]
    public Person $person;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public ?string $sex = null;

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

    public string $bio = '';

    public function mount(Person $person): void
    {
        Gate::authorize('suggest', $person);

        $this->person = $person;
        $this->first_name = $person->first_name;
        $this->middle_name = $person->middle_name ?? '';
        $this->last_name = $person->last_name ?? '';
        $this->sex = $person->sex;
        $this->preferred_name = $person->preferred_name ?? '';
        $this->use_preferred_name_everywhere = $person->use_preferred_name_everywhere;
        $this->dob_precision = $person->dob_precision === 'year' ? 'year' : 'exact';
        $this->dob = $this->dob_precision === 'exact' ? $person->dob?->toDateString() : null;
        $this->dob_year = $this->dob_precision === 'year' ? $person->dob?->format('Y') : null;
        $this->birth_location_id = $person->birth_location_id;
        $this->dod_precision = $person->dod_precision === 'year' ? 'year' : 'exact';
        $this->dod = $this->dod_precision === 'exact' ? $person->dod?->toDateString() : null;
        $this->dod_year = $this->dod_precision === 'year' ? $person->dod?->format('Y') : null;
        $this->death_location_id = $person->death_location_id;
        $this->is_living = $person->is_living;
        $this->bio = $person->bio ?? '';
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

    public function submit(): void
    {
        Gate::authorize('suggest', $this->person);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'in:male,female'],
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
            'bio' => ['nullable', 'string', 'max:20000'],
        ]);

        [$dob, $dobPrecision] = PersonDateInput::resolve($validated['dob_precision'], $validated['dob'], $validated['dob_year']);
        [$dod, $dodPrecision] = PersonDateInput::resolve($validated['dod_precision'], $validated['dod'], $validated['dod_year']);

        $payload = [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'sex' => $validated['sex'],
            'preferred_name' => $validated['preferred_name'] ?: null,
            'use_preferred_name_everywhere' => $validated['preferred_name'] && $validated['use_preferred_name_everywhere'],
            'dob' => $dob,
            'dob_precision' => $dobPrecision,
            'birth_location_id' => $validated['birth_location_id'],
            'dod' => $dod,
            'dod_precision' => $dodPrecision,
            'death_location_id' => $validated['death_location_id'],
            'is_living' => $validated['is_living'],
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
        <x-sex-field model="sex" />

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
        <flux:editor wire:model="bio" :label="__('Bio')" toolbar="heading | bold italic underline strike | bullet ordered blockquote | link" class="**:data-[slot=content]:min-h-56" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Submit suggestion') }}</flux:button>
            <flux:button :href="route('people.show', $person)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
