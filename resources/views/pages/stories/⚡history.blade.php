<?php

use App\Models\Story;
use App\Services\RevisionService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('History')] class extends Component {
    #[Locked]
    public Story $story;

    public function mount(Story $story): void
    {
        Gate::authorize('update', $story);

        $this->story = $story;
    }

    public function rollback(int $revisionId): void
    {
        Gate::authorize('update', $this->story);

        $revision = $this->story->revisions()->findOrFail($revisionId);
        app(RevisionService::class)->rollback($this->story, $revision, Auth::user());

        Flux::toast(variant: 'success', text: __('Rolled back.'));
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading level="1">{{ __('History for') }} {{ $story->title }}</flux:heading>

    <div class="mt-6 space-y-3">
        @forelse ($story->revisions as $revision)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="revision-{{ $revision->id }}">
                <div>
                    <flux:text>{{ $revision->user->name }}</flux:text>
                    <flux:text class="text-xs text-zinc-500">{{ $revision->created_at->format('F j, Y g:ia') }}</flux:text>
                </div>
                @if (! $loop->first)
                    <flux:button wire:click="rollback({{ $revision->id }})" size="sm">{{ __('Roll back to this version') }}</flux:button>
                @else
                    <flux:badge>{{ __('Current') }}</flux:badge>
                @endif
            </div>
        @empty
            <flux:text class="text-zinc-500">{{ __('No revisions yet.') }}</flux:text>
        @endforelse
    </div>
</section>
