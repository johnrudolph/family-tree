<?php

use App\Models\Story;
use App\Services\SuggestionService;
use App\Support\SuggestionDiffer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Review suggestions')] class extends Component {
    #[Locked]
    public Story $story;

    public ?int $editingSuggestionId = null;

    /** @var array<string, mixed> */
    public array $editPayload = [];

    public function mount(Story $story): void
    {
        Gate::authorize('update', $story);

        $this->story = $story;
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'title' => 'Title',
            'body' => 'Story',
            default => $field,
        };
    }

    public function diffsFor(int $suggestionId): array
    {
        $suggestion = $this->story->suggestions()->findOrFail($suggestionId);

        return collect($suggestion->payload)
            ->map(fn ($new, $field) => [
                'label' => $this->fieldLabel($field),
                'html' => SuggestionDiffer::fieldDiffHtml($this->story->{$field}, $new),
            ])
            ->filter(fn ($diff) => $diff['html'] !== '')
            ->values()
            ->all();
    }

    public function editSuggestion(int $suggestionId): void
    {
        $suggestion = $this->story->suggestions()->findOrFail($suggestionId);
        $this->editingSuggestionId = $suggestionId;
        $this->editPayload = $suggestion->payload;
    }

    public function mergeWithChanges(): void
    {
        Gate::authorize('update', $this->story);

        $suggestion = $this->story->suggestions()->findOrFail($this->editingSuggestionId);
        app(SuggestionService::class)->merge($suggestion, Auth::user(), $this->editPayload);

        $this->editingSuggestionId = null;
        Flux::toast(variant: 'success', text: __('Suggestion merged with your changes.'));
    }

    public function merge(int $suggestionId): void
    {
        Gate::authorize('update', $this->story);

        $suggestion = $this->story->suggestions()->findOrFail($suggestionId);
        app(SuggestionService::class)->merge($suggestion, Auth::user());

        Flux::toast(variant: 'success', text: __('Suggestion merged.'));
    }

    public function reject(int $suggestionId): void
    {
        Gate::authorize('update', $this->story);

        $suggestion = $this->story->suggestions()->findOrFail($suggestionId);
        app(SuggestionService::class)->reject($suggestion, Auth::user());

        Flux::toast(variant: 'success', text: __('Suggestion declined.'));
    }
}; ?>

<section class="w-full max-w-2xl">
    @include('partials.diff-styles')

    <flux:heading level="1">{{ __('Suggestions for') }} {{ $story->title }}</flux:heading>

    <div class="mt-6 space-y-6">
        @forelse ($story->pendingSuggestions as $suggestion)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="suggestion-{{ $suggestion->id }}">
                <flux:text class="text-xs text-zinc-500">
                    {{ __('Suggested by') }} {{ $suggestion->user->name }} &middot; {{ $suggestion->created_at->diffForHumans() }}
                </flux:text>

                <div class="mt-3 space-y-3">
                    @foreach ($this->diffsFor($suggestion->id) as $diff)
                        <div>
                            <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ $diff['label'] }}</flux:text>
                            <div class="mt-1 overflow-x-auto rounded border border-zinc-200 dark:border-zinc-700">
                                {!! $diff['html'] !!}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($editingSuggestionId === $suggestion->id)
                    <div class="mt-4 space-y-3 rounded border border-zinc-200 p-3 dark:border-zinc-700">
                        @foreach ($editPayload as $field => $value)
                            @if ($field === 'body')
                                <flux:textarea wire:model="editPayload.{{ $field }}" :label="$this->fieldLabel($field)" rows="10" />
                            @else
                                <flux:input wire:model="editPayload.{{ $field }}" :label="$this->fieldLabel($field)" />
                            @endif
                        @endforeach
                        <div class="flex gap-2">
                            <flux:button wire:click="mergeWithChanges" size="sm" variant="primary">{{ __('Save and merge') }}</flux:button>
                            <flux:button wire:click="$set('editingSuggestionId', null)" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                        </div>
                    </div>
                @else
                    <div class="mt-4 flex gap-2">
                        <flux:button wire:click="merge({{ $suggestion->id }})" size="sm" variant="primary">{{ __('Merge') }}</flux:button>
                        <flux:button wire:click="editSuggestion({{ $suggestion->id }})" size="sm">{{ __('Merge with changes') }}</flux:button>
                        <flux:button wire:click="reject({{ $suggestion->id }})" size="sm" variant="ghost">{{ __('Decline') }}</flux:button>
                    </div>
                @endif
            </div>
        @empty
            <flux:text class="text-zinc-500">{{ __('No pending suggestions.') }}</flux:text>
        @endforelse
    </div>
</section>
