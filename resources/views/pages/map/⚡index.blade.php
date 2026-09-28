<?php

use App\Services\RelationshipService;
use App\Support\MapSerializer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Map')] class extends Component {
    public bool $directRelativesOnly = false;

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
     * Null when the filter is off (or unavailable), meaning "don't
     * filter" — so callers can pass this straight into whereIn()/when().
     *
     * @return Collection<int, int>|null
     */
    private function directRelativeIdsOrNull(): ?Collection
    {
        if (! $this->directRelativesOnly) {
            return null;
        }

        $viewer = Auth::user()->person;

        return $viewer ? app(RelationshipService::class)->directRelativeIds($viewer) : null;
    }

    #[Computed]
    public function points(): array
    {
        return MapSerializer::points($this->directRelativeIdsOrNull());
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

        @include('partials.direct-relatives-toggle')
    </div>

    <div
        wire:ignore
        wire:key="map-{{ $directRelativesOnly ? 'mine' : 'all' }}"
        x-data
        x-init="initMap($el, @js($this->points))"
        class="isolate relative mt-4 h-[75vh] w-full overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"
    ></div>
</section>
