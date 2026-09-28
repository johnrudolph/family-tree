<?php

use App\Models\ChangeLogEntry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Change Log')] class extends Component {
    #[Computed]
    public function entries()
    {
        return ChangeLogEntry::query()->orderByDesc('merged_at')->get();
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading level="1">{{ __('Change Log') }}</flux:heading>
    <flux:subheading>{{ __('What\'s new on the family tree.') }}</flux:subheading>

    <div class="mt-6 space-y-4">
        @forelse ($this->entries as $entry)
            <div class="flex items-start justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700" wire:key="change-log-{{ $entry->id }}">
                <flux:text>{{ $entry->description }}</flux:text>
                <flux:text class="shrink-0 text-xs whitespace-nowrap text-zinc-500">{{ $entry->merged_at->diffForHumans() }}</flux:text>
            </div>
        @empty
            <flux:text class="text-zinc-500">{{ __('Nothing here yet.') }}</flux:text>
        @endforelse
    </div>
</section>
