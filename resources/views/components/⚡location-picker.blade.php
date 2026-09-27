<?php

use App\Models\Location;
use App\Services\GeocodingService;
use App\Services\LocationService;
use Livewire\Component;

new class extends Component
{
    public string $field;

    public ?string $label = null;

    public ?int $locationId = null;

    public string $query = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public ?string $selectedLabel = null;

    public function mount(string $field, ?int $locationId = null, ?string $label = null): void
    {
        $this->field = $field;
        $this->label = $label;
        $this->locationId = $locationId;
        $this->selectedLabel = $locationId ? Location::find($locationId)?->formatted_address : null;
    }

    public function updatedQuery(): void
    {
        $query = trim($this->query);

        if (mb_strlen($query) < 3) {
            $this->results = [];

            return;
        }

        $this->results = app(GeocodingService::class)->search($query);
    }

    public function select(int $index): void
    {
        $candidate = $this->results[$index] ?? null;

        if (! $candidate) {
            return;
        }

        $location = app(LocationService::class)->findOrCreateFromCandidate($candidate);

        $this->locationId = $location->id;
        $this->selectedLabel = $location->formatted_address;
        $this->results = [];
        $this->query = '';

        $this->dispatch('location-selected', field: $this->field, locationId: $location->id);
    }

    public function clear(): void
    {
        $this->locationId = null;
        $this->selectedLabel = null;

        $this->dispatch('location-selected', field: $this->field, locationId: null);
    }
};
?>

<div>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    @if ($selectedLabel)
        <div class="mt-1 flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <flux:text class="truncate">{{ $selectedLabel }}</flux:text>
            <flux:button wire:click="clear" size="xs" variant="ghost">{{ __('Change') }}</flux:button>
        </div>
    @else
        <div class="relative" wire:key="{{ $field }}-location-search">
            <flux:input wire:model.live.debounce.400ms="query" :placeholder="__('Search for a city, address, or country…')" />

            <flux:text wire:loading.delay wire:target="query" class="mt-1 text-xs text-zinc-400">
                {{ __('Searching…') }}
            </flux:text>

            @if (! empty($results))
                <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                    @foreach ($results as $index => $result)
                        <button
                            type="button"
                            wire:click="select({{ $index }})"
                            wire:key="{{ $field }}-location-result-{{ $index }}"
                            class="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                        >
                            {{ $result['formatted_address'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
