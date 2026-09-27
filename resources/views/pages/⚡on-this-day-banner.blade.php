<?php

use App\Models\Person;
use App\Models\Story;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function livingBirthdays()
    {
        return Person::query()
            ->where('is_living', true)
            ->whereNotNull('dob')
            ->whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day)
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Deceased ancestors born on this day in a past year — the living are
     * already covered by the birthday line above, this is the historical
     * "on this day" equivalent for people who've passed.
     */
    #[Computed]
    public function bornOnThisDay()
    {
        return Person::query()
            ->where('is_living', false)
            ->whereNotNull('dob')
            ->whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day)
            ->orderBy('dob')
            ->get();
    }

    #[Computed]
    public function diedOnThisDay()
    {
        return Person::query()
            ->whereNotNull('dod')
            ->whereMonth('dod', now()->month)
            ->whereDay('dod', now()->day)
            ->orderBy('dod')
            ->get();
    }

    /**
     * Year-only stories store a Jan 1 placeholder date (see stories.create),
     * so only exact-precision stories have a real day to match against —
     * otherwise every year-only story would falsely match every Jan 1.
     */
    #[Computed]
    public function storiesOnThisDay()
    {
        return Story::query()
            ->where('start_date_precision', 'exact')
            ->whereMonth('start_date', now()->month)
            ->whereDay('start_date', now()->day)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * The "Happy birthday X, Y & Z!" name list, each name linked to their
     * page — built here (rather than in the Blade) so it can reuse
     * Collection::join()'s "a, b & c" comma/ampersand joining logic.
     */
    #[Computed]
    public function livingBirthdayNamesHtml(): string
    {
        return $this->livingBirthdays
            ->map(fn (Person $person) => '<a href="'.route('people.show', $person).'" wire:navigate class="underline">'.e($person->fullName()).'</a>')
            ->join(', ', ' & ');
    }

    #[Computed]
    public function hasAnything(): bool
    {
        return $this->livingBirthdays->isNotEmpty()
            || $this->bornOnThisDay->isNotEmpty()
            || $this->diedOnThisDay->isNotEmpty()
            || $this->storiesOnThisDay->isNotEmpty();
    }
}; ?>

<div>
    @if ($this->hasAnything)
        <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 dark:border-amber-900 dark:bg-amber-950">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-1 text-center">
                @if ($this->livingBirthdays->isNotEmpty())
                    <flux:text class="text-amber-900 dark:text-amber-200">
                        🎂
                        {{ __('Happy birthday') }}
                        {!! $this->livingBirthdayNamesHtml !!}!
                    </flux:text>
                @endif

                @foreach ($this->bornOnThisDay as $person)
                    <flux:text class="text-amber-900 dark:text-amber-200" wire:key="born-{{ $person->id }}">
                        🎈 {{ __('Born on this day in :year:', ['year' => $person->dob->format('Y')]) }}
                        <a href="{{ route('people.show', $person) }}" wire:navigate class="underline">{{ $person->fullName() }}</a>
                    </flux:text>
                @endforeach

                @foreach ($this->diedOnThisDay as $person)
                    <flux:text class="text-amber-900 dark:text-amber-200" wire:key="died-{{ $person->id }}">
                        🕊️ {{ __('On this day in :year, we lost', ['year' => $person->dod->format('Y')]) }}
                        <a href="{{ route('people.show', $person) }}" wire:navigate class="underline">{{ $person->fullName() }}</a>
                    </flux:text>
                @endforeach

                @foreach ($this->storiesOnThisDay as $story)
                    <flux:text class="text-amber-900 dark:text-amber-200" wire:key="story-{{ $story->id }}">
                        📖 {{ __('On this day in :year:', ['year' => $story->start_date->format('Y')]) }}
                        <a href="{{ route('stories.show', $story) }}" wire:navigate class="underline">{{ $story->title }}</a>
                    </flux:text>
                @endforeach
            </div>
        </div>
    @endif
</div>
