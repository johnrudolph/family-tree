<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\PageEditorService;
use App\Services\RelationshipService;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage relationships')] class extends Component {
    #[Locked]
    public Person $person;

    public string $type = 'parent';

    public string $mode = 'existing';

    public ?int $existingPersonId = null;

    public string $new_first_name = '';

    public string $new_middle_name = '';

    public string $new_last_name = '';

    public ?string $new_dob = null;

    public bool $new_is_living = true;

    public ?string $new_dod = null;

    public string $spouseStatus = 'married';

    /** @var array<int, int> child ids to also link to the new spouse as parent */
    public array $alsoParentOfChildIds = [];

    /** @var array<int, int> spouse ids to also link to the new child as parent */
    public array $alsoCoParentIds = [];

    /** @var array<int, int> parent ids to also link to the new sibling as parent */
    public array $alsoSiblingParentIds = [];

    public function mount(Person $person): void
    {
        Gate::authorize('update', $person);

        $this->person = $person;
    }

    #[Computed]
    public function candidatePeople()
    {
        return app(RelationshipService::class)->candidatesFor($this->person);
    }

    #[Computed]
    public function candidateStepchildren()
    {
        return app(RelationshipService::class)->candidateStepchildren($this->person);
    }

    #[Computed]
    public function candidateCoParents()
    {
        return app(RelationshipService::class)->candidateCoParents($this->person);
    }

    #[Computed]
    public function existingParents()
    {
        return app(RelationshipService::class)->existingParents($this->person);
    }


    #[Computed]
    public function siblings()
    {
        return $this->person->siblings();
    }

    /**
     * Siblings not already shown as a removable row above — only the legacy
     * case of sharing a parent without an explicit sibling relationship yet.
     */
    #[Computed]
    public function derivedOnlySiblings()
    {
        $explicitIds = $this->relationshipRows->pluck('other.id');

        return $this->siblings->reject(fn ($sibling) => $explicitIds->contains($sibling->id));
    }

    #[Computed]
    public function relationshipRows()
    {
        return Relationship::query()
            ->where('person_a_id', $this->person->id)
            ->orWhere('person_b_id', $this->person->id)
            ->with(['personA', 'personB'])
            ->get()
            ->map(function (Relationship $relationship) {
                $isA = $relationship->person_a_id === $this->person->id;
                $other = $isA ? $relationship->personB : $relationship->personA;

                $label = match (true) {
                    $relationship->type === 'parent_child' && $isA => __('Child'),
                    $relationship->type === 'parent_child' && ! $isA => __('Parent'),
                    $relationship->type === 'sibling' => __('Sibling'),
                    default => __('Spouse'),
                };

                return [
                    'id' => $relationship->id,
                    'label' => $label,
                    'other' => $other,
                ];
            });
    }

    /**
     * Reset the "also link" suggestions whenever the relationship type changes,
     * defaulting to everyone selected — the user un-checks the ones that don't apply.
     */
    public function updatedType(): void
    {
        $this->seedAlsoLinkSuggestions();
    }

    /**
     * Populate the "also link" pill defaults for the current type. Called
     * both when the user switches type and after a successful submit — the
     * latter matters because the arrays are otherwise left stale (empty,
     * from the post-submit reset) if someone adds a second relationship of
     * the same type without touching the type radio again, e.g. adding two
     * siblings back to back from an already-open panel.
     */
    private function seedAlsoLinkSuggestions(): void
    {
        $this->alsoParentOfChildIds = $this->type === 'spouse'
            ? $this->candidateStepchildren->pluck('id')->all()
            : [];

        $this->alsoCoParentIds = $this->type === 'child'
            ? $this->candidateCoParents->pluck('id')->all()
            : [];

        $this->alsoSiblingParentIds = $this->type === 'sibling'
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
        abort_unless(in_array($property, ['alsoParentOfChildIds', 'alsoCoParentIds', 'alsoSiblingParentIds'], true), 403);

        $this->{$property} = array_values(array_diff($this->{$property}, [$id]));
    }

    public function addRelationship(): void
    {
        Gate::authorize('update', $this->person);

        $validated = $this->validate([
            'type' => ['required', 'in:parent,child,spouse,sibling'],
            'mode' => ['required', 'in:existing,new'],
            'existingPersonId' => ['required_if:mode,existing', 'nullable', 'exists:people,id'],
            'new_first_name' => ['required_if:mode,new', 'nullable', 'string', 'max:255'],
            'new_middle_name' => ['nullable', 'string', 'max:255'],
            'new_last_name' => ['nullable', 'string', 'max:255'],
            'new_dob' => ['nullable', 'date'],
            'new_is_living' => ['boolean'],
            'new_dod' => ['nullable', 'date'],
            'spouseStatus' => ['required_if:type,spouse', 'in:married,divorced,separated'],
        ]);

        $relationships = app(RelationshipService::class);

        try {
            DB::transaction(function () use ($validated, $relationships) {
                if ($this->mode === 'new') {
                    $other = Person::create([
                        'first_name' => $validated['new_first_name'],
                        'middle_name' => $validated['new_middle_name'] ?: null,
                        'last_name' => $validated['new_last_name'] ?: null,
                        'dob' => $validated['new_dob'] ?: null,
                        'dob_precision' => $validated['new_dob'] ? 'exact' : 'unknown',
                        'is_living' => $validated['new_is_living'],
                        'dod' => $validated['new_is_living'] ? null : ($validated['new_dod'] ?: null),
                        'created_by' => Auth::id(),
                    ]);

                    app(PageEditorService::class)->grantOwner($other, Auth::user());
                } else {
                    $other = Person::findOrFail($validated['existingPersonId']);
                }

                match ($this->type) {
                    'parent' => Relationship::create([
                        'person_a_id' => $other->id,
                        'person_b_id' => $this->person->id,
                        'type' => 'parent_child',
                    ]),
                    'child' => Relationship::create([
                        'person_a_id' => $this->person->id,
                        'person_b_id' => $other->id,
                        'type' => 'parent_child',
                    ]),
                    'spouse' => Relationship::create([
                        'person_a_id' => $this->person->id,
                        'person_b_id' => $other->id,
                        'type' => 'spouse',
                        'status' => $validated['spouseStatus'],
                    ]),
                    'sibling' => $relationships->addSibling($this->person, $other),
                };

                if ($this->type === 'spouse') {
                    foreach (Person::query()->whereIn('id', $this->alsoParentOfChildIds)->get() as $child) {
                        $relationships->linkParentChildIfMissing($other, $child);
                    }
                } elseif ($this->type === 'child') {
                    foreach (Person::query()->whereIn('id', $this->alsoCoParentIds)->get() as $spouse) {
                        $relationships->linkParentChildIfMissing($spouse, $other);
                    }
                } elseif ($this->type === 'sibling') {
                    foreach (Person::query()->whereIn('id', $this->alsoSiblingParentIds)->get() as $parent) {
                        $relationships->linkParentChildIfMissing($parent, $other);
                    }
                }
            });
        } catch (QueryException) {
            Flux::toast(variant: 'danger', text: __('That relationship already exists.'));

            return;
        }

        $this->reset(['existingPersonId', 'new_first_name', 'new_middle_name', 'new_last_name', 'new_dob', 'new_is_living', 'new_dod']);
        unset($this->relationshipRows, $this->candidatePeople, $this->candidateStepchildren, $this->candidateCoParents, $this->existingParents);
        $this->seedAlsoLinkSuggestions();

        Flux::toast(variant: 'success', text: __('Relationship added.'));
    }

    public function removeRelationship(int $relationshipId): void
    {
        Gate::authorize('update', $this->person);

        Relationship::query()
            ->where(fn ($query) => $query
                ->where('person_a_id', $this->person->id)
                ->orWhere('person_b_id', $this->person->id))
            ->findOrFail($relationshipId)
            ->delete();

        unset($this->relationshipRows);

        Flux::toast(variant: 'success', text: __('Relationship removed.'));
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading level="1">{{ __('Relationships for') }} {{ $person->fullName() }}</flux:heading>

    <div class="mt-6 space-y-2">
        @forelse ($this->relationshipRows as $row)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="rel-{{ $row['id'] }}">
                <div class="flex items-center gap-3">
                    <flux:badge size="sm">{{ $row['label'] }}</flux:badge>
                    <a href="{{ route('people.show', $row['other']) }}" wire:navigate class="hover:underline">
                        {{ $row['other']->fullName() }}
                    </a>
                </div>
                <flux:button wire:click="removeRelationship({{ $row['id'] }})" size="sm" variant="ghost" icon="x-mark" />
            </div>
        @empty
            <flux:text class="text-zinc-500">{{ __('No relationships recorded yet.') }}</flux:text>
        @endforelse

        @foreach ($this->derivedOnlySiblings as $sibling)
            <div class="flex items-center justify-between rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-600" wire:key="sibling-{{ $sibling->id }}">
                <div class="flex items-center gap-3">
                    <flux:badge size="sm">{{ __('Sibling') }}</flux:badge>
                    <a href="{{ route('people.show', $sibling) }}" wire:navigate class="hover:underline">
                        {{ $sibling->fullName() }}
                    </a>
                </div>
                <flux:text class="text-xs text-zinc-500">{{ __('via shared parent') }}</flux:text>
            </div>
        @endforeach
    </div>

    <flux:separator class="my-8" />

    <flux:heading level="2">{{ __('Add a relationship') }}</flux:heading>

    <form wire:submit="addRelationship" class="mt-4 flex flex-col gap-6">
        <flux:radio.group wire:model.live="type" :label="__('Relationship')">
            <flux:radio value="parent" label="{{ __('Parent of').' '.$person->fullName() }}" />
            <flux:radio value="child" label="{{ __('Child of').' '.$person->fullName() }}" />
            <flux:radio value="spouse" label="{{ __('Spouse of').' '.$person->fullName() }}" />
            <flux:radio value="sibling" label="{{ __('Sibling of').' '.$person->fullName() }}" />
        </flux:radio.group>

        <div x-show="$wire.type === 'spouse'">
            <flux:select wire:model="spouseStatus" :label="__('Status')">
                <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
            </flux:select>

            <x-relationship-pills
                :label="__('Also mark as parent of')"
                :hint="__('Click × to remove anyone this new spouse isn\'t a parent of.')"
                property="alsoParentOfChildIds"
                :candidates="$this->candidateStepchildren"
                :selected-ids="$alsoParentOfChildIds"
            />
        </div>

        <div x-show="$wire.type === 'child'">
            <x-relationship-pills
                :label="__('Also mark as parent')"
                :hint="__('Click × to remove anyone who isn\'t also a parent of this child.')"
                property="alsoCoParentIds"
                :candidates="$this->candidateCoParents"
                :selected-ids="$alsoCoParentIds"
            />
        </div>

        <div x-show="$wire.type === 'sibling'">
            <x-relationship-pills
                :label="__('Also link as parent')"
                :hint="__('Click × to remove anyone who doesn\'t apply.')"
                property="alsoSiblingParentIds"
                :candidates="$this->existingParents"
                :selected-ids="$alsoSiblingParentIds"
            />

            @if ($this->siblings->isNotEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('They\'ll also become a sibling of:') }}
                        {{ $this->siblings->map->fullName()->join(', ') }}
                    </flux:text>
                </div>
            @endif
        </div>

        <flux:separator />

        <flux:radio.group wire:model="mode" :label="__('Who?')">
            <flux:radio value="existing" label="{{ __('Someone already on the tree') }}" />
            <flux:radio value="new" label="{{ __('A new person, not yet on the tree') }}" />
        </flux:radio.group>

        <div x-show="$wire.mode === 'existing'">
            <flux:select variant="combobox" wire:model="existingPersonId" :label="__('Person')" :placeholder="__('Search people…')" clearable>
                @foreach ($this->candidatePeople as $candidate)
                    <flux:select.option value="{{ $candidate->id }}">{{ $candidate->fullName() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div x-show="$wire.mode === 'new'" class="flex flex-col gap-6">
            <flux:input wire:model="new_first_name" :label="__('First name')" />
            <flux:input wire:model="new_middle_name" :label="__('Middle name')" />
            <flux:input wire:model="new_last_name" :label="__('Last name')" />
            <flux:input wire:model="new_dob" type="date" :label="__('Date of birth')" />
            <flux:checkbox wire:model="new_is_living" :label="__('Living')" />
            <div x-show="! $wire.new_is_living">
                <flux:input wire:model="new_dod" type="date" :label="__('Date of death')" />
            </div>
        </div>

        <div>
            <flux:button type="submit" variant="primary">{{ __('Add relationship') }}</flux:button>
        </div>
    </form>
</section>
