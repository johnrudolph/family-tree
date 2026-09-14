<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\PageEditorService;
use App\Services\RelationshipService;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
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

    public string $spouseStatus = 'married';

    /** @var array<int, int> child ids to also link to the new spouse as parent */
    public array $alsoParentOfChildIds = [];

    /** @var array<int, int> spouse ids to also link to the new child as parent */
    public array $alsoCoParentIds = [];

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
    public function personHasParents(): bool
    {
        return $this->existingParents->isNotEmpty();
    }

    #[Computed]
    public function siblings()
    {
        return $this->person->siblings();
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
    public function updatedType(string $value): void
    {
        $this->alsoParentOfChildIds = $value === 'spouse'
            ? $this->candidateStepchildren->pluck('id')->all()
            : [];

        $this->alsoCoParentIds = $value === 'child'
            ? $this->candidateCoParents->pluck('id')->all()
            : [];
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
            'spouseStatus' => ['required_if:type,spouse', 'in:married,divorced,separated'],
        ]);

        if ($this->mode === 'new') {
            $other = Person::create([
                'first_name' => $validated['new_first_name'],
                'middle_name' => $validated['new_middle_name'] ?: null,
                'last_name' => $validated['new_last_name'] ?: null,
                'dob' => $validated['new_dob'] ?: null,
                'dob_precision' => $validated['new_dob'] ? 'exact' : 'unknown',
                'is_living' => true,
                'created_by' => Auth::id(),
            ]);

            app(PageEditorService::class)->grantOwner($other, Auth::user());
        } else {
            $other = Person::findOrFail($validated['existingPersonId']);
        }

        $relationships = app(RelationshipService::class);

        try {
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
                'sibling' => null,
            };
        } catch (QueryException) {
            Flux::toast(variant: 'danger', text: __('That relationship already exists.'));

            return;
        }

        if ($this->type === 'spouse') {
            foreach (Person::query()->whereIn('id', $this->alsoParentOfChildIds)->get() as $child) {
                $relationships->linkParentChildIfMissing($other, $child);
            }
        } elseif ($this->type === 'child') {
            foreach (Person::query()->whereIn('id', $this->alsoCoParentIds)->get() as $spouse) {
                $relationships->linkParentChildIfMissing($spouse, $other);
            }
        } elseif ($this->type === 'sibling') {
            foreach ($relationships->existingParents($this->person) as $parent) {
                $relationships->linkParentChildIfMissing($parent, $other);
            }
        }

        $this->reset(['existingPersonId', 'new_first_name', 'new_middle_name', 'new_last_name', 'new_dob', 'alsoParentOfChildIds', 'alsoCoParentIds']);
        unset($this->relationshipRows, $this->candidatePeople, $this->candidateStepchildren, $this->candidateCoParents);

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

        @foreach ($this->siblings as $sibling)
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
            @if ($this->personHasParents)
                <flux:radio value="sibling" label="{{ __('Sibling of').' '.$person->fullName() }}" />
            @endif
        </flux:radio.group>

        @if ($type === 'spouse')
            <flux:select wire:model="spouseStatus" :label="__('Status')">
                <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
            </flux:select>

            @if ($this->candidateStepchildren->isNotEmpty())
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950">
                    <flux:text class="font-medium text-blue-900 dark:text-blue-200">
                        💡 {{ __('Suggested: also mark as parent of') }}
                    </flux:text>
                    <flux:text class="text-xs text-blue-700 dark:text-blue-400">
                        {{ __('All checked by default — uncheck anyone this new spouse isn\'t a parent of.') }}
                    </flux:text>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach ($this->candidateStepchildren as $child)
                            <flux:checkbox
                                wire:model="alsoParentOfChildIds"
                                value="{{ $child->id }}"
                                :label="$child->fullName()"
                            />
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        @if ($type === 'child' && $this->candidateCoParents->isNotEmpty())
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950">
                <flux:text class="font-medium text-blue-900 dark:text-blue-200">💡 {{ __('Suggested: also mark as parent') }}</flux:text>
                <flux:text class="text-xs text-blue-700 dark:text-blue-400">
                    {{ __('All checked by default — uncheck anyone who isn\'t also a parent of this child.') }}
                </flux:text>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($this->candidateCoParents as $spouse)
                        <flux:checkbox
                            wire:model="alsoCoParentIds"
                            value="{{ $spouse->id }}"
                            :label="$spouse->fullName()"
                        />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($type === 'sibling')
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950">
                <flux:text class="font-medium text-blue-900 dark:text-blue-200">
                    💡 {{ __('This will also link them to :parents as parents.', ['parents' => $this->existingParents->map->fullName()->join(' and ')]) }}
                </flux:text>
            </div>
        @endif

        <flux:radio.group wire:model.live="mode" :label="__('Who?')">
            <flux:radio value="existing" label="{{ __('Someone already on the tree') }}" />
            <flux:radio value="new" label="{{ __('A new person, not yet on the tree') }}" />
        </flux:radio.group>

        @if ($mode === 'existing')
            <flux:select wire:model="existingPersonId" :label="__('Person')">
                <flux:select.option value="">{{ __('Select a person…') }}</flux:select.option>
                @foreach ($this->candidatePeople as $candidate)
                    <flux:select.option value="{{ $candidate->id }}">{{ $candidate->fullName() }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:input wire:model="new_first_name" :label="__('First name')" />
            <flux:input wire:model="new_middle_name" :label="__('Middle name')" />
            <flux:input wire:model="new_last_name" :label="__('Last name')" />
            <flux:input wire:model="new_dob" type="date" :label="__('Date of birth')" />
        @endif

        <div>
            <flux:button type="submit" variant="primary">{{ __('Add relationship') }}</flux:button>
        </div>
    </form>
</section>
