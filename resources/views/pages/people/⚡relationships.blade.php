<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\PageEditorService;
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

    public string $new_last_name = '';

    public string $spouseStatus = 'married';

    public function mount(Person $person): void
    {
        Gate::authorize('update', $person);

        $this->person = $person;
    }

    #[Computed]
    public function candidatePeople()
    {
        return Person::query()
            ->where('id', '!=', $this->person->id)
            ->orderBy('first_name')
            ->get();
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

    public function addRelationship(): void
    {
        Gate::authorize('update', $this->person);

        $validated = $this->validate([
            'type' => ['required', 'in:parent,child,spouse'],
            'mode' => ['required', 'in:existing,new'],
            'existingPersonId' => ['required_if:mode,existing', 'nullable', 'exists:people,id'],
            'new_first_name' => ['required_if:mode,new', 'nullable', 'string', 'max:255'],
            'new_last_name' => ['nullable', 'string', 'max:255'],
            'spouseStatus' => ['required_if:type,spouse', 'in:married,divorced,separated'],
        ]);

        if ($this->mode === 'new') {
            $other = Person::create([
                'first_name' => $validated['new_first_name'],
                'last_name' => $validated['new_last_name'] ?: null,
                'is_living' => true,
                'created_by' => Auth::id(),
            ]);

            app(PageEditorService::class)->grantOwner($other, Auth::user());
        } else {
            $other = Person::findOrFail($validated['existingPersonId']);
        }

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
            };
        } catch (QueryException) {
            Flux::toast(variant: 'danger', text: __('That relationship already exists.'));

            return;
        }

        $this->reset(['existingPersonId', 'new_first_name', 'new_last_name']);
        unset($this->relationshipRows, $this->candidatePeople);

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
    </div>

    <flux:separator class="my-8" />

    <flux:heading level="2">{{ __('Add a relationship') }}</flux:heading>

    <form wire:submit="addRelationship" class="mt-4 flex flex-col gap-6">
        <flux:radio.group wire:model.live="type" :label="__('Relationship')">
            <flux:radio value="parent" label="{{ __('Parent of').' '.$person->fullName() }}" />
            <flux:radio value="child" label="{{ __('Child of').' '.$person->fullName() }}" />
            <flux:radio value="spouse" label="{{ __('Spouse of').' '.$person->fullName() }}" />
        </flux:radio.group>

        @if ($type === 'spouse')
            <flux:select wire:model="spouseStatus" :label="__('Status')">
                <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
            </flux:select>
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
            <flux:input wire:model="new_last_name" :label="__('Last name')" />
        @endif

        <div>
            <flux:button type="submit" variant="primary">{{ __('Add relationship') }}</flux:button>
        </div>
    </form>
</section>
