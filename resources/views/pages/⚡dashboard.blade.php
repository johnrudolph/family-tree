<?php

use App\Models\Person;
use App\Models\Revision;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function me(): ?Person
    {
        return Auth::user()->person;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function missingProfileFields(): array
    {
        return $this->me?->missingCoreDataFields() ?? [];
    }

    #[Computed]
    public function missingProfilePhoto(): bool
    {
        return $this->me && ! $this->me->photoUrl();
    }

    /**
     * The last handful of "someone created/updated something" events across
     * people and stories, newest first — a person's own creation and any
     * later edits to it are separate events, not merged.
     *
     * @return Collection<int, array{actor: string, verb: string, label: string, url: string, at: \Illuminate\Support\Carbon}>
     */
    #[Computed]
    public function activity(): Collection
    {
        $peopleCreated = Person::query()
            ->with('creator')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Person $person) => [
                'actor' => $person->creator?->name ?? __('Someone'),
                'verb' => __('created'),
                'label' => $person->fullName(),
                'url' => route('people.show', $person),
                'at' => $person->created_at,
            ]);

        $storiesCreated = Story::query()
            ->with('creator')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Story $story) => [
                'actor' => $story->creator?->name ?? __('Someone'),
                'verb' => __('added the story'),
                'label' => $story->title,
                'url' => route('stories.show', $story),
                'at' => $story->created_at,
            ]);

        $updates = Revision::query()
            ->with(['user', 'revisable'])
            ->latest('created_at')
            ->limit(15)
            ->get()
            ->filter(fn (Revision $revision) => $revision->revisable !== null)
            ->map(fn (Revision $revision) => [
                'actor' => $revision->user?->name ?? __('Someone'),
                'verb' => $revision->revisable instanceof Story ? __('updated the story') : __('updated'),
                'label' => $revision->revisable instanceof Story ? $revision->revisable->title : $revision->revisable->fullName(),
                'url' => $revision->revisable instanceof Story ? route('stories.show', $revision->revisable) : route('people.show', $revision->revisable),
                'at' => $revision->created_at,
            ]);

        return $peopleCreated->concat($storiesCreated)->concat($updates)
            ->sortByDesc('at')
            ->take(10)
            ->values();
    }

    #[Computed]
    public function recentStories()
    {
        return Story::query()->with('creator')->latest()->limit(5)->get();
    }

    #[Computed]
    public function recentMembers()
    {
        return User::query()->with('person')->latest()->limit(5)->get();
    }
}; ?>

<div class="flex w-full flex-col gap-8">
    <livewire:pages::on-this-day-banner />

    <div class="flex items-center gap-4">
        @if ($this->me)
            <x-person-avatar :person="$this->me" size="xl" />
        @endif
        <div>
            <flux:heading level="1">{{ __('Welcome back,') }} {{ Auth::user()->name }}</flux:heading>
            @if ($this->me)
                <flux:text class="text-zinc-500">
                    {{ __('You\'re') }}
                    <a href="{{ route('people.show', $this->me) }}" wire:navigate class="text-blue-600 hover:underline dark:text-blue-400">
                        {{ $this->me->fullName() }}
                    </a>
                    {{ __('on the tree.') }}
                </flux:text>
            @endif
        </div>
    </div>

    @if ($this->me && ($this->missingProfilePhoto || $this->missingProfileFields !== []))
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950">
            <flux:heading level="2" size="sm" class="text-amber-900 dark:text-amber-200">{{ __('Finish setting up your profile') }}</flux:heading>

            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900 dark:text-amber-200">
                @if ($this->missingProfilePhoto)
                    <li>{{ __('Add a profile photo') }}</li>
                @endif
                @foreach ($this->missingProfileFields as $field)
                    <li>
                        {{ match ($field) {
                            'sex' => __('Add your sex'),
                            'dob' => __('Add your date of birth'),
                            'birth_location' => __('Add your place of birth'),
                            default => __('Add missing details'),
                        } }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($this->missingProfilePhoto)
                    <flux:button :href="route('people.enrich', $this->me)" wire:navigate size="sm">
                        {{ __('Add a photo') }}
                    </flux:button>
                @endif
                @if ($this->missingProfileFields !== [])
                    <flux:button :href="route('people.edit', $this->me)" wire:navigate size="sm" variant="primary">
                        {{ __('Complete your details') }}
                    </flux:button>
                @endif
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="2" size="sm">{{ __('Recent activity') }}</flux:heading>

            <div class="mt-3 space-y-3">
                @forelse ($this->activity as $event)
                    <div class="flex items-baseline justify-between gap-3">
                        <flux:text class="text-sm">
                            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $event['actor'] }}</span>
                            {{ $event['verb'] }}
                            <a href="{{ $event['url'] }}" wire:navigate class="text-blue-600 hover:underline dark:text-blue-400">{{ $event['label'] }}</a>
                        </flux:text>
                        <flux:text class="shrink-0 text-xs whitespace-nowrap text-zinc-500">{{ $event['at']->diffForHumans() }}</flux:text>
                    </div>
                @empty
                    <flux:text class="text-zinc-500">{{ __('Nothing yet.') }}</flux:text>
                @endforelse
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading level="2" size="sm">{{ __('Recently added stories') }}</flux:heading>

                <div class="mt-3 space-y-2">
                    @forelse ($this->recentStories as $story)
                        <div class="flex items-baseline justify-between gap-3">
                            <a href="{{ route('stories.show', $story) }}" wire:navigate class="truncate text-sm text-blue-600 hover:underline dark:text-blue-400">
                                {{ $story->title }}
                            </a>
                            <flux:text class="shrink-0 text-xs whitespace-nowrap text-zinc-500">{{ $story->created_at->diffForHumans() }}</flux:text>
                        </div>
                    @empty
                        <flux:text class="text-zinc-500">{{ __('No stories yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading level="2" size="sm">{{ __('Recently joined') }}</flux:heading>

                <div class="mt-3 space-y-2">
                    @forelse ($this->recentMembers as $member)
                        <div class="flex items-baseline justify-between gap-3">
                            @if ($member->person)
                                <a href="{{ route('people.show', $member->person) }}" wire:navigate class="truncate text-sm text-blue-600 hover:underline dark:text-blue-400">
                                    {{ $member->name }}
                                </a>
                            @else
                                <flux:text class="truncate text-sm">{{ $member->name }}</flux:text>
                            @endif
                            <flux:text class="shrink-0 text-xs whitespace-nowrap text-zinc-500">{{ $member->created_at->diffForHumans() }}</flux:text>
                        </div>
                    @empty
                        <flux:text class="text-zinc-500">{{ __('No one yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
