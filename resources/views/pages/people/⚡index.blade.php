<?php

use App\Models\Person;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('People')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function people()
    {
        return Person::query()
            ->with('media')
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

    <flux:input x-ref="searchInput" wire:model.live.debounce.300ms="search" :placeholder="__('Search by name… (⌘F)')" class="mt-4 max-w-sm" />

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
