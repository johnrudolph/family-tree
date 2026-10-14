<?php

use App\Models\Person;
use App\Services\RelationshipService;
use App\Support\MapSerializer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Map')] class extends Component {
    public bool $directRelativesOnly = false;

    /** @var array<int, int> */
    public array $selectedPersonIds = [];

    public function mount(): void
    {
        $this->directRelativesOnly = (bool) session('direct_relatives_only', false);
    }

    public function updatedDirectRelativesOnly(): void
    {
        session(['direct_relatives_only' => $this->directRelativesOnly]);
    }

    /**
     * Whether the "only show my direct relatives" toggle can be shown at
     * all — meaningless for a viewer with no linked person on the tree.
     */
    #[Computed]
    public function canFilterToDirectRelatives(): bool
    {
        return Auth::user()->person_id !== null;
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function filterablePeople(): Collection
    {
        return Person::query()->orderBy('first_name')->orderBy('last_name')->get();
    }

    /**
     * Null when neither filter is active, meaning "don't filter" — so
     * callers can pass this straight into whereIn()/when(). When both the
     * direct-relatives toggle and a specific-people selection are active,
     * they narrow each other down to their intersection.
     *
     * @return Collection<int, int>|null
     */
    private function filteredPersonIdsOrNull(): ?Collection
    {
        $ids = null;

        if ($this->directRelativesOnly) {
            $viewer = Auth::user()->person;
            $ids = $viewer ? app(RelationshipService::class)->directRelativeIds($viewer) : null;
        }

        if ($this->selectedPersonIds !== []) {
            $selected = collect($this->selectedPersonIds)->map(fn ($id) => (int) $id)->values();
            $ids = $ids ? $ids->intersect($selected)->values() : $selected;
        }

        return $ids;
    }

    #[Computed]
    public function points(): array
    {
        return MapSerializer::points($this->filteredPersonIdsOrNull());
    }
}; ?>

<section class="flex w-full flex-col">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading level="1">{{ __('Map') }}</flux:heading>
            <flux:subheading>
                {{ __('Every birth, death, and story we know a location for.') }}
            </flux:subheading>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
            @include('partials.direct-relatives-toggle')
            @include('partials.people-filter')
        </div>
    </div>

    <div
        wire:ignore
        wire:key="map-{{ $directRelativesOnly ? 'mine' : 'all' }}-{{ implode('-', $selectedPersonIds) }}"
        x-data
        x-init="initMap($el, @js($this->points))"
        class="isolate relative mt-4 h-[75vh] w-full overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"
    ></div>
</section>
