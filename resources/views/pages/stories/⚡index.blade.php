<?php

use App\Models\Story;
use App\Support\MediaUrl;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stories')] class extends Component {
    use WithPagination;

    #[Computed]
    public function stories()
    {
        return Story::query()->with(['creator', 'location', 'media'])->orderByDesc('start_date')->paginate(15);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <flux:heading level="1">{{ __('Stories') }}</flux:heading>
        <flux:button :href="route('stories.create')" wire:navigate size="sm" variant="primary">{{ __('New story') }}</flux:button>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->stories as $story)
            @php($featured = $story->featuredImage())
            <a href="{{ route('stories.show', $story) }}" wire:navigate
               class="block overflow-hidden rounded-lg border border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                @if ($featured)
                    <img src="{{ MediaUrl::of($featured) }}" class="aspect-video w-full object-cover" alt="">
                @else
                    <div class="flex aspect-video w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon.book-open class="size-8 text-zinc-400" />
                    </div>
                @endif

                <div class="p-4">
                    <flux:heading level="3" class="flex items-center gap-1.5">
                        {{ $story->title }}
                        @if ($story->hasAudio())
                            <flux:icon.volume-2 class="size-4 shrink-0 text-zinc-400" />
                        @endif
                    </flux:heading>
                    <flux:text class="text-xs text-zinc-500">
                        {{ $story->start_date_precision === 'year' ? $story->start_date->format('Y') : $story->start_date->format('F j, Y') }}
                        @if ($story->end_date)
                            &ndash; {{ $story->end_date_precision === 'year' ? $story->end_date->format('Y') : $story->end_date->format('F j, Y') }}
                        @endif
                        @if ($story->location)
                            &middot; {{ $story->location->shortLabel() }}
                        @endif
                    </flux:text>
                    <flux:text class="text-xs text-zinc-500">{{ __('By') }} {{ $story->creator->name }}</flux:text>
                </div>
            </a>
        @empty
            <flux:text class="text-zinc-500">{{ __('No stories yet.') }}</flux:text>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->stories->links() }}
    </div>
</section>
