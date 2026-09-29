<?php

use App\Models\Story;
use App\Support\MediaUrl;
use App\Support\StoryBodyParser;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Story $story;

    public function mount(Story $story): void
    {
        $this->story = $story;
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Gate::allows('update', $this->story);
    }

    #[Computed]
    public function pendingSuggestionCount(): int
    {
        return $this->canEdit ? $this->story->pendingSuggestions()->count() : 0;
    }

    #[Computed]
    public function galleryUrls(): array
    {
        return $this->story->galleryMedia()->map(fn ($media) => MediaUrl::of($media))->values()->all();
    }

    #[Computed]
    public function audioUrl(): ?string
    {
        $media = $this->story->audioMedia();

        return $media ? MediaUrl::of($media) : null;
    }
}; ?>

<section class="w-full max-w-2xl">
    <div class="flex items-start justify-between">
        <flux:heading level="1" class="flex items-center gap-2">
            {{ $story->title }}
            @if ($this->audioUrl)
                <flux:icon.volume-2 class="size-5 shrink-0 text-zinc-400" />
            @endif
        </flux:heading>
        <div class="flex gap-2">
            @if ($this->canEdit)
                <flux:button :href="route('stories.edit', $story)" wire:navigate size="sm">{{ __('Edit') }}</flux:button>
                <flux:button :href="route('stories.editors', $story)" wire:navigate size="sm">{{ __('Editors') }}</flux:button>
                <flux:button :href="route('stories.suggestions', $story)" wire:navigate size="sm">
                    {{ __('Suggestions') }}
                    @if ($this->pendingSuggestionCount > 0)
                        <flux:badge size="sm" color="amber">{{ $this->pendingSuggestionCount }}</flux:badge>
                    @endif
                </flux:button>
                <flux:button :href="route('stories.history', $story)" wire:navigate size="sm">{{ __('History') }}</flux:button>
            @else
                <flux:button :href="route('stories.suggest', $story)" wire:navigate size="sm">{{ __('Suggest an edit') }}</flux:button>
            @endif
        </div>
    </div>
    <flux:text class="text-zinc-500">
        {{ $story->start_date_precision === 'year' ? $story->start_date->format('Y') : $story->start_date->format('F j, Y') }}
        @if ($story->end_date)
            &ndash; {{ $story->end_date_precision === 'year' ? $story->end_date->format('Y') : $story->end_date->format('F j, Y') }}
        @endif
        @if ($story->location)
            &middot; {{ $story->location->shortLabel() }}
        @endif
    </flux:text>
    <flux:text class="text-xs text-zinc-500">
        {{ __('By') }} {{ $story->creator->name }} &middot; {{ $story->created_at->diffForHumans() }}
    </flux:text>

    @if ($story->people->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($story->people as $person)
                <flux:badge :href="route('people.show', $person)" wire:navigate>{{ $person->fullName() }}</flux:badge>
            @endforeach
        </div>
    @endif

    @if ($this->audioUrl)
        <div
            class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
            wire:ignore
            x-data
            x-init="initAudioPlayer($el, @js($this->audioUrl))"
        >
            <div data-audio-waveform></div>
            <div class="mt-3 flex items-center gap-3">
                <button
                    type="button"
                    data-audio-play
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600 [&[data-playing]_[data-icon-play]]:hidden [&:not([data-playing])_[data-icon-pause]]:hidden"
                >
                    <flux:icon.play data-icon-play class="size-4" />
                    <flux:icon.pause data-icon-pause class="size-4" />
                </button>

                <flux:text class="text-xs tabular-nums text-zinc-500">
                    <span data-audio-current>0:00</span> / <span data-audio-duration>0:00</span>
                </flux:text>

                <div class="ml-auto flex items-center gap-3">
                    <select data-audio-speed class="rounded-md border-zinc-200 bg-transparent text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                        <option value="0.75">0.75x</option>
                        <option value="1" selected>1x</option>
                        <option value="1.25">1.25x</option>
                        <option value="1.5">1.5x</option>
                        <option value="2">2x</option>
                    </select>

                    <div class="flex items-center gap-1.5">
                        <flux:icon.volume-2 class="size-4 text-zinc-400" />
                        <input type="range" data-audio-volume min="0" max="1" step="0.05" value="1" class="w-20 accent-blue-500">
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($this->galleryUrls)
        <div class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($this->galleryUrls as $i => $mediaUrl)
                <button type="button" onclick="openLightbox(@js($this->galleryUrls), {{ $i }})">
                    <img src="{{ $mediaUrl }}" class="aspect-square rounded-lg object-cover" alt="">
                </button>
            @endforeach
        </div>
    @endif

    <div class="prose prose-zinc dark:prose-invert mt-6 max-w-none">
        {!! StoryBodyParser::render($story->body ?? '') !!}
    </div>
</section>
