<?php

use App\Models\Person;
use App\Services\RelationshipService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('People')] class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $directRelativesOnly = false;

    public function mount(): void
    {
        $this->directRelativesOnly = (bool) session('direct_relatives_only', false);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDirectRelativesOnly(): void
    {
        session(['direct_relatives_only' => $this->directRelativesOnly]);
        $this->resetPage();
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
    public function people()
    {
        return Person::query()
            ->with('media')
            ->when($this->directRelativeIdsOrNull(), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->when($this->search, fn ($query) => $query
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%"))
            ->orderBy('first_name')
            ->paginate(24);
    }
}; ?>

<section
    class="w-full"
    x-data
    x-on:keydown.window="if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'f') { event.preventDefault(); $refs.searchInput.focus(); $refs.searchInput.select(); }"
>
    <div class="flex items-center justify-between">
        <flux:heading level="1">{{ __('People') }}</flux:heading>
        @if (auth()->user()->is_admin)
            <div class="flex gap-2">
                <flux:button :href="route('people.create')" wire:navigate size="sm" variant="primary">{{ __('Add person') }}</flux:button>
                <flux:button :href="route('invites.create')" wire:navigate size="sm">{{ __('Invite a member') }}</flux:button>
            </div>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4">
        <flux:input x-ref="searchInput" wire:model.live.debounce.300ms="search" :placeholder="__('Search by name… (⌘F)')" class="max-w-sm" />
        @include('partials.direct-relatives-toggle')
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->people as $person)
            <a href="{{ route('people.show', $person) }}" wire:navigate
               class="flex items-center gap-3 rounded-lg border border-zinc-200 p-4 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                <x-person-avatar :person="$person" />
                <div class="min-w-0">
                    <flux:text class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $person->fullName() }}</flux:text>
                    <flux:text class="text-xs text-zinc-500">
                        {{ $person->is_living ? __('Living') : __('Deceased') }}
                    </flux:text>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $this->people->links() }}
    </div>
</section>
