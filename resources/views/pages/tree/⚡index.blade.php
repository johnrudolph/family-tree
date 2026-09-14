<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\PageEditorService;
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

    public string $relNewLastName = '';

    public string $relSpouseStatus = 'married';

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
        if (! $this->selectedPersonId) {
            return collect();
        }

        return Person::query()->where('id', '!=', $this->selectedPersonId)->orderBy('first_name')->get();
    }

    public function selectPerson(int $id): void
    {
        $this->selectedPersonId = $id;
        $this->search = '';
        unset($this->candidatePeople);

        $this->dispatch('tree-center-on', id: $id);
    }

    public function addRelationship(): void
    {
        abort_unless($this->selectedPerson, 404);
        Gate::authorize('update', $this->selectedPerson);

        $validated = $this->validate([
            'relType' => ['required', 'in:parent,child,spouse'],
            'relMode' => ['required', 'in:existing,new'],
            'relExistingPersonId' => ['required_if:relMode,existing', 'nullable', 'exists:people,id'],
            'relNewFirstName' => ['required_if:relMode,new', 'nullable', 'string', 'max:255'],
            'relNewLastName' => ['nullable', 'string', 'max:255'],
            'relSpouseStatus' => ['required_if:relType,spouse', 'in:married,divorced,separated'],
        ]);

        $person = $this->selectedPerson;

        if ($this->relMode === 'new') {
            $other = Person::create([
                'first_name' => $validated['relNewFirstName'],
                'last_name' => $validated['relNewLastName'] ?: null,
                'is_living' => true,
                'created_by' => Auth::id(),
            ]);

            app(PageEditorService::class)->grantOwner($other, Auth::user());
        } else {
            $other = Person::findOrFail($validated['relExistingPersonId']);
        }

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
            };
        } catch (QueryException) {
            Flux::toast(variant: 'danger', text: __('That relationship already exists.'));

            return;
        }

        $this->reset(['relExistingPersonId', 'relNewFirstName', 'relNewLastName']);
        unset($this->candidatePeople);

        Flux::toast(variant: 'success', text: __('Relationship added.'));

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

            <flux:button :href="route('people.show', $this->selectedPerson)" wire:navigate size="sm" class="mt-3 w-full">
                {{ __('View full page') }}
            </flux:button>

            @if ($this->canEditSelected)
                <flux:separator class="my-4" />
                <flux:heading level="2" size="sm">{{ __('Add a relationship') }}</flux:heading>

                <form wire:submit="addRelationship" class="mt-3 flex flex-col gap-3">
                    <flux:radio.group wire:model.live="relType">
                        <flux:radio value="parent" label="{{ __('Parent') }}" />
                        <flux:radio value="child" label="{{ __('Child') }}" />
                        <flux:radio value="spouse" label="{{ __('Spouse') }}" />
                    </flux:radio.group>

                    @if ($relType === 'spouse')
                        <flux:select wire:model="relSpouseStatus">
                            <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                            <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                            <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
                        </flux:select>
                    @endif

                    <flux:radio.group wire:model.live="relMode">
                        <flux:radio value="existing" label="{{ __('Existing person') }}" />
                        <flux:radio value="new" label="{{ __('New person') }}" />
                    </flux:radio.group>

                    @if ($relMode === 'existing')
                        <flux:select wire:model="relExistingPersonId">
                            <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
                            @foreach ($this->candidatePeople as $candidate)
                                <flux:select.option value="{{ $candidate->id }}">{{ $candidate->fullName() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="relNewFirstName" :placeholder="__('First name')" />
                        <flux:input wire:model="relNewLastName" :placeholder="__('Last name')" />
                    @endif

                    <flux:button type="submit" variant="primary" size="sm">{{ __('Add relationship') }}</flux:button>
                </form>
            @endif
        @endif
    </div>
</section>
