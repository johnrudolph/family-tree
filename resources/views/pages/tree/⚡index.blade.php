<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\PageEditorService;
use App\Services\RelationshipService;
use App\Support\FamilyTreeSerializer;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Family Tree')] class extends Component {
    public string $search = '';

    public ?int $selectedPersonId = null;

    public string $relType = 'parent';

    public string $relMode = 'existing';

    public ?int $relExistingPersonId = null;

    public string $relNewFirstName = '';

    public string $relNewMiddleName = '';

    public string $relNewLastName = '';

    public ?string $relNewDob = null;

    public string $relSpouseStatus = 'married';

    /** @var array<int, int> child ids to also link to the new spouse as parent */
    public array $relAlsoParentOfChildIds = [];

    /** @var array<int, int> spouse ids to also link to the new child as parent */
    public array $relAlsoCoParentIds = [];

    /** @var array<int, int> parent ids to also link to the new sibling as parent */
    public array $relAlsoSiblingParentIds = [];

    #[Computed]
    public function treeData(): array
    {
        return FamilyTreeSerializer::toChartData();
    }

    #[Computed]
    public function mainId(): ?int
    {
        return Auth::user()->person_id;
    }

    #[Computed]
    public function searchResults()
    {
        if ($this->search === '') {
            return collect();
        }

        return Person::query()
            ->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%"))
            ->orderBy('first_name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function selectedPerson(): ?Person
    {
        return $this->selectedPersonId ? Person::find($this->selectedPersonId) : null;
    }

    #[Computed]
    public function canEditSelected(): bool
    {
        return $this->selectedPerson && Gate::allows('update', $this->selectedPerson);
    }

    #[Computed]
    public function candidatePeople()
    {
        return $this->selectedPerson ? app(RelationshipService::class)->candidatesFor($this->selectedPerson) : collect();
    }

    #[Computed]
    public function candidateStepchildren()
    {
        return $this->selectedPerson ? app(RelationshipService::class)->candidateStepchildren($this->selectedPerson) : collect();
    }

    #[Computed]
    public function candidateCoParents()
    {
        return $this->selectedPerson ? app(RelationshipService::class)->candidateCoParents($this->selectedPerson) : collect();
    }

    #[Computed]
    public function existingParents()
    {
        return $this->selectedPerson ? app(RelationshipService::class)->existingParents($this->selectedPerson) : collect();
    }

    #[Computed]
    public function selectedPersonHasParents(): bool
    {
        return $this->existingParents->isNotEmpty();
    }

    #[Computed]
    public function selectedPersonSiblings()
    {
        return $this->selectedPerson ? $this->selectedPerson->siblings() : collect();
    }

    #[Computed]
    public function selectedPersonRelationshipRows()
    {
        if (! $this->selectedPerson) {
            return collect();
        }

        $person = $this->selectedPerson;

        return Relationship::query()
            ->where('person_a_id', $person->id)
            ->orWhere('person_b_id', $person->id)
            ->with(['personA', 'personB'])
            ->get()
            ->map(function (Relationship $relationship) use ($person) {
                $isA = $relationship->person_a_id === $person->id;
                $other = $isA ? $relationship->personB : $relationship->personA;

                $label = match (true) {
                    $relationship->type === 'parent_child' && $isA => __('Child'),
                    $relationship->type === 'parent_child' && ! $isA => __('Parent'),
                    default => __('Spouse'),
                };

                return [
                    'id' => $relationship->id,
                    'label' => $label,
                    'other' => $other,
                ];
            });
    }

    public function selectPerson(int $id): void
    {
        $this->selectedPersonId = $id;
        $this->search = '';
        $this->relType = 'parent';
        unset($this->candidatePeople, $this->candidateStepchildren, $this->candidateCoParents, $this->existingParents);

        $this->dispatch('tree-center-on', id: $id);
    }

    /**
     * Reset the "also link" suggestions whenever the relationship type changes,
     * defaulting to everyone selected — the user un-checks the ones that don't apply.
     */
    public function updatedRelType(string $value): void
    {
        $this->relAlsoParentOfChildIds = $value === 'spouse'
            ? $this->candidateStepchildren->pluck('id')->all()
            : [];

        $this->relAlsoCoParentIds = $value === 'child'
            ? $this->candidateCoParents->pluck('id')->all()
            : [];

        $this->relAlsoSiblingParentIds = $value === 'sibling'
            ? $this->existingParents->pluck('id')->all()
            : [];
    }

    /**
     * Remove a suggested "also link" pill before creating the relationship.
     * $property is restricted to the known suggestion arrays since it's
     * client-controlled via the pill component's wire:click call.
     */
    public function removeSuggestion(string $property, int $id): void
    {
        abort_unless(in_array($property, ['relAlsoParentOfChildIds', 'relAlsoCoParentIds', 'relAlsoSiblingParentIds'], true), 403);

        $this->{$property} = array_values(array_diff($this->{$property}, [$id]));
    }

    public function addRelationship(): void
    {
        abort_unless($this->selectedPerson, 404);
        Gate::authorize('update', $this->selectedPerson);

        $validated = $this->validate([
            'relType' => ['required', 'in:parent,child,spouse,sibling'],
            'relMode' => ['required', 'in:existing,new'],
            'relExistingPersonId' => ['required_if:relMode,existing', 'nullable', 'exists:people,id'],
            'relNewFirstName' => ['required_if:relMode,new', 'nullable', 'string', 'max:255'],
            'relNewMiddleName' => ['nullable', 'string', 'max:255'],
            'relNewLastName' => ['nullable', 'string', 'max:255'],
            'relNewDob' => ['nullable', 'date'],
            'relSpouseStatus' => ['required_if:relType,spouse', 'in:married,divorced,separated'],
        ]);

        $person = $this->selectedPerson;

        if ($this->relMode === 'new') {
            $other = Person::create([
                'first_name' => $validated['relNewFirstName'],
                'middle_name' => $validated['relNewMiddleName'] ?: null,
                'last_name' => $validated['relNewLastName'] ?: null,
                'dob' => $validated['relNewDob'] ?: null,
                'dob_precision' => $validated['relNewDob'] ? 'exact' : 'unknown',
                'is_living' => true,
                'created_by' => Auth::id(),
            ]);

            app(PageEditorService::class)->grantOwner($other, Auth::user());
        } else {
            $other = Person::findOrFail($validated['relExistingPersonId']);
        }

        $relationships = app(RelationshipService::class);

        try {
            match ($this->relType) {
                'parent' => Relationship::create([
                    'person_a_id' => $other->id,
                    'person_b_id' => $person->id,
                    'type' => 'parent_child',
                ]),
                'child' => Relationship::create([
                    'person_a_id' => $person->id,
                    'person_b_id' => $other->id,
                    'type' => 'parent_child',
                ]),
                'spouse' => Relationship::create([
                    'person_a_id' => $person->id,
                    'person_b_id' => $other->id,
                    'type' => 'spouse',
                    'status' => $validated['relSpouseStatus'],
                ]),
                'sibling' => null,
            };
        } catch (QueryException) {
            Flux::toast(variant: 'danger', text: __('That relationship already exists.'));

            return;
        }

        if ($this->relType === 'spouse') {
            foreach (Person::query()->whereIn('id', $this->relAlsoParentOfChildIds)->get() as $child) {
                $relationships->linkParentChildIfMissing($other, $child);
            }
        } elseif ($this->relType === 'child') {
            foreach (Person::query()->whereIn('id', $this->relAlsoCoParentIds)->get() as $spouse) {
                $relationships->linkParentChildIfMissing($spouse, $other);
            }
        } elseif ($this->relType === 'sibling') {
            foreach (Person::query()->whereIn('id', $this->relAlsoSiblingParentIds)->get() as $parent) {
                $relationships->linkParentChildIfMissing($parent, $other);
            }
        }

        $this->reset(['relExistingPersonId', 'relNewFirstName', 'relNewMiddleName', 'relNewLastName', 'relNewDob', 'relAlsoParentOfChildIds', 'relAlsoCoParentIds', 'relAlsoSiblingParentIds']);
        unset($this->candidatePeople, $this->candidateStepchildren, $this->candidateCoParents);

        Flux::toast(variant: 'success', text: __('Relationship added.'));

        $this->dispatch('tree-data-updated', data: FamilyTreeSerializer::toChartData());
    }

    public function removeRelationship(int $relationshipId): void
    {
        abort_unless($this->selectedPerson, 404);
        Gate::authorize('update', $this->selectedPerson);

        Relationship::query()
            ->where(fn ($query) => $query
                ->where('person_a_id', $this->selectedPerson->id)
                ->orWhere('person_b_id', $this->selectedPerson->id))
            ->findOrFail($relationshipId)
            ->delete();

        unset($this->selectedPersonRelationshipRows, $this->selectedPersonSiblings, $this->existingParents, $this->candidatePeople);

        Flux::toast(variant: 'success', text: __('Relationship removed.'));

        $this->dispatch('tree-data-updated', data: FamilyTreeSerializer::toChartData());
    }
}; ?>

<section
    class="flex h-[80vh] w-full gap-4"
    x-data
    x-on:tree-center-on.window="window.familyTreeCenterOn($event.detail.id)"
    x-on:tree-data-updated.window="window.familyTreeUpdateData($event.detail.data)"
    x-on:family-tree-card-click.window="$wire.selectPerson($event.detail.id)"
>
    <div
        wire:ignore
        class="min-w-0 flex-1 rounded-lg border border-zinc-200 dark:border-zinc-700"
        x-init="initFamilyTree($refs.chart, @js($this->treeData), { mainId: @js($this->mainId) })"
    >
        <div x-ref="chart" class="h-full w-full" id="family-tree-chart"></div>
    </div>

    <div class="w-80 shrink-0 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading level="1" size="sm">{{ __('Family Tree') }}</flux:heading>

        <flux:input wire:model.live.debounce.200ms="search" :placeholder="__('Search people…')" class="mt-3" />

        @if ($search !== '')
            <div class="mt-2 space-y-1">
                @forelse ($this->searchResults as $result)
                    <button
                        type="button"
                        wire:click="selectPerson({{ $result->id }})"
                        class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    >
                        {{ $result->fullName() }}
                    </button>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No matches.') }}</flux:text>
                @endforelse
            </div>
        @endif

        @if ($this->selectedPerson)
            <flux:separator class="my-4" />

            <div class="flex items-center gap-3">
                <x-person-avatar :person="$this->selectedPerson" size="lg" />
                <div class="min-w-0">
                    <flux:text class="truncate font-medium">{{ $this->selectedPerson->fullName() }}</flux:text>
                    <flux:text class="text-xs text-zinc-500">
                        {{ $this->selectedPerson->is_living ? __('Living') : __('Deceased') }}
                    </flux:text>
                </div>
            </div>

            <div class="mt-2">
                <x-person-account-badges :person="$this->selectedPerson" />
            </div>

            <flux:button :href="route('people.show', $this->selectedPerson)" wire:navigate size="sm" class="mt-3 w-full">
                {{ __('View full page') }}
            </flux:button>

            @if ($this->selectedPersonRelationshipRows->isNotEmpty())
                <div class="mt-3 space-y-1">
                    @foreach ($this->selectedPersonRelationshipRows as $row)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700" wire:key="rel-{{ $row['id'] }}">
                            <div class="flex min-w-0 items-center gap-2">
                                <flux:badge size="sm">{{ $row['label'] }}</flux:badge>
                                <a href="{{ route('people.show', $row['other']) }}" wire:navigate class="truncate text-sm hover:underline">
                                    {{ $row['other']->fullName() }}
                                </a>
                            </div>
                            @if ($this->canEditSelected)
                                <flux:button wire:click="removeRelationship({{ $row['id'] }})" size="sm" variant="ghost" icon="x-mark" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($this->canEditSelected)
                <flux:modal.trigger name="add-relationship">
                    <flux:button size="sm" variant="primary" class="mt-2 w-full">
                        {{ __('Add a relationship') }}
                    </flux:button>
                </flux:modal.trigger>

                <flux:modal name="add-relationship" class="w-96" x-on:tree-data-updated.window="$flux.modal('add-relationship').close()">
                    <flux:heading level="2" size="sm">{{ __('Add a relationship') }}</flux:heading>

                    <form wire:submit="addRelationship" class="mt-3 flex flex-col gap-3">
                    <flux:radio.group wire:model.live="relType">
                        <flux:radio value="parent" label="{{ __('Parent') }}" />
                        <flux:radio value="child" label="{{ __('Child') }}" />
                        <flux:radio value="spouse" label="{{ __('Spouse') }}" />
                        @if ($this->selectedPersonHasParents)
                            <flux:radio value="sibling" label="{{ __('Sibling') }}" />
                        @endif
                    </flux:radio.group>

                    @if ($relType === 'spouse')
                        <flux:select wire:model="relSpouseStatus">
                            <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                            <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                            <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
                        </flux:select>

                        <x-relationship-pills
                            :label="__('Also mark as parent of')"
                            :hint="__('Click × to remove anyone who doesn\'t apply.')"
                            property="relAlsoParentOfChildIds"
                            :candidates="$this->candidateStepchildren"
                            :selected-ids="$relAlsoParentOfChildIds"
                        />
                    @endif

                    @if ($relType === 'child')
                        <x-relationship-pills
                            :label="__('Also mark as parent')"
                            :hint="__('Click × to remove anyone who doesn\'t apply.')"
                            property="relAlsoCoParentIds"
                            :candidates="$this->candidateCoParents"
                            :selected-ids="$relAlsoCoParentIds"
                        />
                    @endif

                    @if ($relType === 'sibling')
                        <x-relationship-pills
                            :label="__('Also link as parent')"
                            :hint="__('Click × to remove anyone who doesn\'t apply.')"
                            property="relAlsoSiblingParentIds"
                            :candidates="$this->existingParents"
                            :selected-ids="$relAlsoSiblingParentIds"
                        />

                        @if ($this->selectedPersonSiblings->isNotEmpty())
                            <div class="rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('They\'ll also become a sibling of:') }}
                                    {{ $this->selectedPersonSiblings->map->fullName()->join(', ') }}
                                </flux:text>
                            </div>
                        @endif
                    @endif

                    <flux:separator />

                    <flux:radio.group wire:model.live="relMode">
                        <flux:radio value="existing" label="{{ __('Existing person') }}" />
                        <flux:radio value="new" label="{{ __('New person') }}" />
                    </flux:radio.group>

                    @if ($relMode === 'existing')
                        <flux:select variant="combobox" wire:model="relExistingPersonId" :placeholder="__('Search people…')" clearable>
                            @foreach ($this->candidatePeople as $candidate)
                                <flux:select.option value="{{ $candidate->id }}">{{ $candidate->fullName() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="relNewFirstName" :placeholder="__('First name')" />
                        <flux:input wire:model="relNewMiddleName" :placeholder="__('Middle name')" />
                        <flux:input wire:model="relNewLastName" :placeholder="__('Last name')" />
                        <flux:input wire:model="relNewDob" type="date" :placeholder="__('Date of birth')" />
                    @endif

                    <flux:button type="submit" variant="primary" size="sm">{{ __('Add relationship') }}</flux:button>
                    </form>
                </flux:modal>
            @endif
        @endif
    </div>
</section>
