<?php

use App\Models\Story;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stories')] class extends Component {
    use WithPagination;

    #[Computed]
    public function stories()
    {
        return Story::query()->orderByDesc('start_date')->paginate(15);
    }
}; ?>

<section class="w-full max-w-2xl">
    <div class="flex items-center justify-between">
        <flux:heading level="1">{{ __('Stories') }}</flux:heading>
        <flux:button :href="route('stories.create')" wire:navigate size="sm" variant="primary">{{ __('New story') }}</flux:button>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($this->stories as $story)
            <a href="{{ route('stories.show', $story) }}" wire:navigate
               class="block rounded-lg border border-zinc-200 p-4 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                <flux:heading level="3">{{ $story->title }}</flux:heading>
                <flux:text class="text-xs text-zinc-500">
                    {{ $story->start_date_precision === 'year' ? $story->start_date->format('Y') : $story->start_date->format('F j, Y') }}
                    &middot; {{ __('By') }} {{ $story->creator->name }}
                </flux:text>
            </a>
        @empty
            <flux:text class="text-zinc-500">{{ __('No stories yet.') }}</flux:text>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->stories->links() }}
    </div>
</section>
