<?php

use App\Models\Person;
use App\Support\MarkdownRenderer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Person $person;

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    #[Computed]
    public function bioHtml(): string
    {
        return MarkdownRenderer::toHtml($this->person->bio);
    }

    #[Computed]
    public function isSelf(): bool
    {
        return Auth::user()->person_id === $this->person->id;
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Gate::allows('update', $this->person);
    }

    #[Computed]
    public function canManageEnrichment(): bool
    {
        return Gate::allows('manageEnrichment', $this->person);
    }

    #[Computed]
    public function pendingSuggestionCount(): int
    {
        return $this->canEdit ? $this->person->pendingSuggestions()->count() : 0;
    }
}; ?>

<section class="w-full max-w-3xl">
    <div class="flex items-start gap-4">
        <x-person-avatar :person="$person" size="xl" />

        <div class="min-w-0 flex-1">
            <flux:heading level="1">{{ $person->fullName() }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ $person->is_living ? __('Living') : __('Deceased') }}
                @if ($person->dob)
                    &middot; {{ __('Born') }} {{ $person->dob->format('F j, Y') }}
                @endif
                @if (! $person->is_living && $person->dod)
                    &middot; {{ __('Died') }} {{ $person->dod->format('F j, Y') }}
                @endif
            </flux:text>
        </div>

        <div class="flex gap-2">
            @if ($this->isSelf && ! $person->hasConsented())
                <flux:button :href="route('people.enrich', $person)" wire:navigate size="sm" variant="primary">
                    {{ __('Add my details') }}
                </flux:button>
            @elseif ($this->isSelf)
                <flux:button :href="route('people.enrich', $person)" wire:navigate size="sm">
                    {{ __('Edit my details') }}
                </flux:button>
            @elseif ($this->canManageEnrichment)
                <flux:button :href="route('people.enrich', $person)" wire:navigate size="sm">
                    {{ __('Memorial photo & details') }}
                </flux:button>
            @endif

            @if ($this->canEdit)
                <flux:button :href="route('people.edit', $person)" wire:navigate size="sm">
                    {{ __('Edit') }}
                </flux:button>
                <flux:button :href="route('people.relationships', $person)" wire:navigate size="sm">
                    {{ __('Relationships') }}
                </flux:button>
                <flux:button :href="route('people.editors', $person)" wire:navigate size="sm">
                    {{ __('Editors') }}
                </flux:button>
                <flux:button :href="route('people.suggestions', $person)" wire:navigate size="sm">
                    {{ __('Suggestions') }}
                    @if ($this->pendingSuggestionCount > 0)
                        <flux:badge size="sm" color="amber">{{ $this->pendingSuggestionCount }}</flux:badge>
                    @endif
                </flux:button>
                <flux:button :href="route('people.history', $person)" wire:navigate size="sm">
                    {{ __('History') }}
                </flux:button>
            @else
                <flux:button :href="route('people.suggest', $person)" wire:navigate size="sm">
                    {{ __('Suggest an edit') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($this->isSelf && ! $person->hasConsented())
        <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
            <flux:text class="text-amber-900 dark:text-amber-200">
                {{ __('Only your name, dob, and relationships are shown by default. You can choose to add a photo, contact info, and social links — nobody else can add these for you.') }}
            </flux:text>
        </div>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-8 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <flux:heading level="2">{{ __('About') }}</flux:heading>
            <div class="prose prose-zinc dark:prose-invert mt-2 max-w-none">
                @if ($this->bioHtml)
                    {!! $this->bioHtml !!}
                @else
                    <flux:text class="text-zinc-500">{{ __('No bio yet.') }}</flux:text>
                @endif
            </div>

            @if ($person->canShowEnrichment() && ($person->address || $person->phone || $person->contact_email || $person->social_links))
                <flux:heading level="2" class="mt-8">{{ __('Contact') }}</flux:heading>
                <dl class="mt-2 space-y-1">
                    @if ($person->contact_email)
                        <div><flux:text class="text-zinc-500">{{ __('Email') }}:</flux:text> {{ $person->contact_email }}</div>
                    @endif
                    @if ($person->phone)
                        <div><flux:text class="text-zinc-500">{{ __('Phone') }}:</flux:text> {{ $person->phone }}</div>
                    @endif
                    @if ($person->address)
                        <div><flux:text class="text-zinc-500">{{ __('Address') }}:</flux:text> {{ $person->address }}</div>
                    @endif
                    @foreach ($person->social_links ?? [] as $link)
                        <div><a href="{{ $link }}" class="text-blue-600 hover:underline dark:text-blue-400" target="_blank" rel="noopener">{{ $link }}</a></div>
                    @endforeach
                </dl>
            @endif
        </div>

        <div>
            <flux:heading level="2">{{ __('Relationships') }}</flux:heading>
            <div class="mt-2 space-y-4">
                @php($parents = $person->parents())
                @php($children = $person->children())
                @php($spouses = $person->spouses())
                @php($siblings = $person->siblings())

                @if ($parents->isNotEmpty())
                    <div>
                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Parents') }}</flux:text>
                        @foreach ($parents as $parent)
                            <a href="{{ route('people.show', $parent) }}" wire:navigate class="block text-sm hover:underline">{{ $parent->fullName() }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($siblings->isNotEmpty())
                    <div>
                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Siblings') }}</flux:text>
                        @foreach ($siblings as $sibling)
                            <a href="{{ route('people.show', $sibling) }}" wire:navigate class="block text-sm hover:underline">{{ $sibling->fullName() }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($spouses->isNotEmpty())
                    <div>
                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Spouses') }}</flux:text>
                        @foreach ($spouses as $spouse)
                            <a href="{{ route('people.show', $spouse) }}" wire:navigate class="block text-sm hover:underline">{{ $spouse->fullName() }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($children->isNotEmpty())
                    <div>
                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Children') }}</flux:text>
                        @foreach ($children as $child)
                            <a href="{{ route('people.show', $child) }}" wire:navigate class="block text-sm hover:underline">{{ $child->fullName() }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($parents->isEmpty() && $spouses->isEmpty() && $children->isEmpty() && $siblings->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('No relationships recorded yet.') }}</flux:text>
                @endif
            </div>

            @if ($person->stories->isNotEmpty())
                <flux:heading level="2" class="mt-8">{{ __('Stories') }}</flux:heading>
                <div class="mt-2 space-y-1">
                    @foreach ($person->stories as $story)
                        <a href="{{ route('stories.show', $story) }}" wire:navigate class="block text-sm hover:underline">{{ $story->title }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
