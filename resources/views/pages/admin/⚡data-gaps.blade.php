<?php

use App\Models\Person;
use App\Services\RevisionService;
use App\Support\PersonDateInput;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Data gaps')] class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
    }

    public ?int $editingPersonId = null;

    public ?string $editDob = null;

    public string $editDobPrecision = 'exact';

    public ?string $editDobYear = null;

    public bool $editDobUnknown = false;

    public ?int $editBirthLocationId = null;

    public bool $editBirthLocationUnknown = false;

    public ?string $editDod = null;

    public string $editDodPrecision = 'exact';

    public ?string $editDodYear = null;

    public bool $editDodUnknown = false;

    public ?int $editDeathLocationId = null;

    public bool $editDeathLocationUnknown = false;

    /**
     * Bumped every time a modal opens so the location pickers (nested
     * Livewire components with their own internal state) always remount
     * fresh instead of showing a previous person's stale selection.
     */
    public int $formInstance = 0;

    /**
     * @return array<int, array{person: Person, gaps: array<int, string>}>
     */
    #[Computed]
    public function people(): array
    {
        return Person::query()
            ->missingCoreData()
            ->orderBy('first_name')
            ->get()
            ->map(fn (Person $person) => [
                'person' => $person,
                'gaps' => $person->missingCoreDataFields(),
            ])
            ->all();
    }

    #[Computed]
    public function editingPerson(): ?Person
    {
        return $this->editingPersonId ? Person::find($this->editingPersonId) : null;
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'dob' => __('Date of birth'),
            'birth_location' => __('Birth location'),
            'dod' => __('Date of death'),
            'death_location' => __('Death location'),
            default => $field,
        };
    }

    public function edit(int $personId): void
    {
        $person = Person::findOrFail($personId);
        Gate::authorize('update', $person);

        $this->editingPersonId = $personId;
        $this->reset(['editDob', 'editDobPrecision', 'editDobYear', 'editDobUnknown', 'editBirthLocationId', 'editBirthLocationUnknown', 'editDod', 'editDodPrecision', 'editDodYear', 'editDodUnknown', 'editDeathLocationId', 'editDeathLocationUnknown']);
        $this->formInstance++;
        $this->resetValidation();

        Flux::modal('data-gap-edit')->show();
    }

    #[On('location-selected')]
    public function onLocationSelected(string $field, ?int $locationId): void
    {
        match ($field) {
            'gapBirth' => $this->editBirthLocationId = $locationId,
            'gapDeath' => $this->editDeathLocationId = $locationId,
            default => null,
        };
    }

    public function save(): void
    {
        $person = $this->editingPerson;
        abort_unless($person, 404);
        Gate::authorize('update', $person);

        $validated = $this->validate([
            'editDobPrecision' => ['required', 'in:exact,year'],
            'editDob' => ['nullable', 'date'],
            'editDobYear' => ['nullable', 'integer', 'min:1000', 'max:'.now()->year],
            'editDobUnknown' => ['boolean'],
            'editBirthLocationId' => ['nullable', 'integer', 'exists:locations,id'],
            'editBirthLocationUnknown' => ['boolean'],
            'editDodPrecision' => ['required', 'in:exact,year'],
            'editDod' => ['nullable', 'date'],
            'editDodYear' => ['nullable', 'integer', 'min:1000', 'max:'.now()->year],
            'editDodUnknown' => ['boolean'],
            'editDeathLocationId' => ['nullable', 'integer', 'exists:locations,id'],
            'editDeathLocationUnknown' => ['boolean'],
        ]);

        $gaps = $person->missingCoreDataFields();
        $data = [];

        if (in_array('dob', $gaps, true)) {
            if ($validated['editDobUnknown']) {
                $data['dob_unknown'] = true;
            } else {
                [$dob, $dobPrecision] = PersonDateInput::resolve($validated['editDobPrecision'], $validated['editDob'], $validated['editDobYear']);

                if ($dob) {
                    $data['dob'] = $dob;
                    $data['dob_precision'] = $dobPrecision;
                }
            }
        }

        if (in_array('birth_location', $gaps, true)) {
            if ($validated['editBirthLocationUnknown']) {
                $data['birth_location_unknown'] = true;
            } elseif ($validated['editBirthLocationId']) {
                $data['birth_location_id'] = $validated['editBirthLocationId'];
            }
        }

        if (in_array('dod', $gaps, true)) {
            if ($validated['editDodUnknown']) {
                $data['dod_unknown'] = true;
            } else {
                [$dod, $dodPrecision] = PersonDateInput::resolve($validated['editDodPrecision'], $validated['editDod'], $validated['editDodYear']);

                if ($dod) {
                    $data['dod'] = $dod;
                    $data['dod_precision'] = $dodPrecision;
                }
            }
        }

        if (in_array('death_location', $gaps, true)) {
            if ($validated['editDeathLocationUnknown']) {
                $data['death_location_unknown'] = true;
            } elseif ($validated['editDeathLocationId']) {
                $data['death_location_id'] = $validated['editDeathLocationId'];
            }
        }

        if ($data !== []) {
            $person->update($data);
            app(RevisionService::class)->record($person, Auth::user(), $data);
        }

        unset($this->people);
        $this->editingPersonId = null;

        Flux::modal('data-gap-edit')->close();
        Flux::toast(variant: 'success', text: __('Saved.'));
    }
}; ?>

<section class="w-full max-w-3xl">
    <flux:heading level="1">{{ __('Data gaps') }}</flux:heading>
    <flux:subheading>
        {{ __('Every person missing a birth date, birth place, or — once recorded as deceased — a death date or place. Click one to fill in what you know.') }}
    </flux:subheading>

    <div class="mt-6 flex flex-col gap-2">
        @forelse ($this->people as $item)
            <flux:modal.trigger name="data-gap-edit" wire:key="gap-trigger-{{ $item['person']->id }}">
                <button
                    type="button"
                    wire:click="edit({{ $item['person']->id }})"
                    class="flex w-full items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 text-left hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                >
                    <div class="min-w-0">
                        <flux:text class="font-medium">{{ $item['person']->fullName() }}</flux:text>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($item['gaps'] as $gap)
                                <flux:badge size="sm" color="amber">{{ $this->fieldLabel($gap) }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                    <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400" />
                </button>
            </flux:modal.trigger>
        @empty
            <flux:text class="text-zinc-500">{{ __('No gaps — every recorded person has their core facts filled in.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="data-gap-edit" class="w-96" @close="$set('editingPersonId', null)">
        @if ($this->editingPerson)
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:heading level="2" size="lg">{{ $this->editingPerson->fullName() }}</flux:heading>

                @if (in_array('dob', $this->editingPerson->missingCoreDataFields(), true))
                    <div class="flex flex-col gap-2">
                        <div x-show="! $wire.editDobUnknown">
                            <x-date-precision-field label="{{ __('Date of birth') }}" date-model="editDob" precision-model="editDobPrecision" year-model="editDobYear" />
                        </div>
                        <flux:checkbox wire:model.live="editDobUnknown" :label="__('Unknown — no need to guess')" />
                    </div>
                @endif

                @if (in_array('birth_location', $this->editingPerson->missingCoreDataFields(), true))
                    <div class="flex flex-col gap-2">
                        <div x-show="! $wire.editBirthLocationUnknown">
                            <livewire:location-picker field="gapBirth" :location-id="$editBirthLocationId" :label="__('Birth location')" wire:key="gap-birth-location-{{ $formInstance }}" />
                        </div>
                        <flux:checkbox wire:model.live="editBirthLocationUnknown" :label="__('Unknown — no need to guess')" />
                    </div>
                @endif

                @if (in_array('dod', $this->editingPerson->missingCoreDataFields(), true))
                    <div class="flex flex-col gap-2">
                        <div x-show="! $wire.editDodUnknown">
                            <x-date-precision-field label="{{ __('Date of death') }}" date-model="editDod" precision-model="editDodPrecision" year-model="editDodYear" />
                        </div>
                        <flux:checkbox wire:model.live="editDodUnknown" :label="__('Unknown — no need to guess')" />
                    </div>
                @endif

                @if (in_array('death_location', $this->editingPerson->missingCoreDataFields(), true))
                    <div class="flex flex-col gap-2">
                        <div x-show="! $wire.editDeathLocationUnknown">
                            <livewire:location-picker field="gapDeath" :location-id="$editDeathLocationId" :label="__('Death location')" wire:key="gap-death-location-{{ $formInstance }}" />
                        </div>
                        <flux:checkbox wire:model.live="editDeathLocationUnknown" :label="__('Unknown — no need to guess')" />
                    </div>
                @endif

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                    <flux:modal.close>
                        <flux:button size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        @endif
    </flux:modal>
</section>
