<?php

use App\Models\Story;
use App\Support\MarkdownRenderer;
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
    public function bodyHtml(): string
    {
        return MarkdownRenderer::toHtml($this->story->body);
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
}; ?>

<section class="w-full max-w-2xl">
    <div class="flex items-start justify-between">
        <flux:heading level="1">{{ $story->title }}</flux:heading>
        <div class="flex gap-2">
            @if ($this->canEdit)
                <flux:button :href="route('stories.edit', $story)" wire:navigate size="sm">{{ __('Edit') }}</flux:button>
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

    @if ($story->galleryMedia()->isNotEmpty())
        <div class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($story->galleryMedia() as $media)
                <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener">
                    <img src="{{ $media->getUrl() }}" class="aspect-square rounded-lg object-cover" alt="">
                </a>
            @endforeach
        </div>
    @endif

    <div class="prose prose-zinc dark:prose-invert mt-6 max-w-none">
        {!! $this->bodyHtml !!}
    </div>
</section>
