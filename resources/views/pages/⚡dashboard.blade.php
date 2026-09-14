<?php

use App\Models\PageEditor;
use App\Models\Person;
use App\Models\Story;
use App\Models\Suggestion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function me(): Person
    {
        return Auth::user()->person;
    }

    #[Computed]
    public function peopleCount(): int
    {
        return Person::query()->count();
    }

    #[Computed]
    public function storiesCount(): int
    {
        return Story::query()->count();
    }

    #[Computed]
    public function pendingSuggestionCount(): int
    {
        $personIds = PageEditor::query()
            ->where('user_id', Auth::id())
            ->where('editable_type', Person::class)
            ->pluck('editable_id');

        $storyIds = PageEditor::query()
            ->where('user_id', Auth::id())
            ->where('editable_type', Story::class)
            ->pluck('editable_id');

        return Suggestion::query()
            ->where('status', 'pending')
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('suggestable_type', Person::class)->whereIn('suggestable_id', $personIds))
                ->orWhere(fn ($q) => $q->where('suggestable_type', Story::class)->whereIn('suggestable_id', $storyIds)))
            ->count();
    }
}; ?>

<div class="flex w-full flex-col gap-8">
    <div class="flex items-center gap-4">
        <x-person-avatar :person="$this->me" size="xl" />
        <div>
            <flux:heading level="1">{{ __('Welcome back,') }} {{ Auth::user()->name }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ __('You\'re') }}
                <a href="{{ route('people.show', $this->me) }}" wire:navigate class="text-blue-600 hover:underline dark:text-blue-400">
                    {{ $this->me->fullName() }}
                </a>
                {{ __('on the tree.') }}
            </flux:text>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('tree.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
            <flux:heading level="2">{{ $this->peopleCount }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('People on the tree') }}</flux:text>
        </a>
        <a href="{{ route('stories.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
            <flux:heading level="2">{{ $this->storiesCount }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Stories') }}</flux:text>
        </a>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="2">{{ $this->pendingSuggestionCount }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Suggestions awaiting your review') }}</flux:text>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('people.show', $this->me)" wire:navigate variant="primary">{{ __('View my page') }}</flux:button>
        <flux:button :href="route('tree.index')" wire:navigate>{{ __('Explore the family tree') }}</flux:button>
        <flux:button :href="route('people.relationships', $this->me)" wire:navigate>{{ __('Add my relationships') }}</flux:button>
        <flux:button :href="route('stories.create')" wire:navigate>{{ __('Write a story') }}</flux:button>
        @if (Auth::user()->is_admin)
            <flux:button :href="route('invites.create')" wire:navigate>{{ __('Invite a member') }}</flux:button>
        @endif
    </div>
</div>
