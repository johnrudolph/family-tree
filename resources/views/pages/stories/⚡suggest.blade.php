<?php

use App\Models\Story;
use App\Services\SuggestionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Suggest an edit')] class extends Component {
    #[Locked]
    public Story $story;

    public string $title = '';

    public string $body = '';

    public string $start_date_precision = 'exact';

    public ?string $start_date = null;

    public ?string $start_year = null;

    public bool $has_end_date = false;

    public string $end_date_precision = 'exact';

    public ?string $end_date = null;

    public ?string $end_year = null;

    public function mount(Story $story): void
    {
        Gate::authorize('suggest', $story);

        $this->story = $story;
        $this->title = $story->title;
        $this->body = $story->body ?? '';
        $this->start_date_precision = $story->start_date_precision;
        $this->start_date = $story->start_date_precision === 'exact' ? $story->start_date->toDateString() : null;
        $this->start_year = $story->start_date_precision === 'year' ? $story->start_date->format('Y') : null;
        $this->has_end_date = $story->end_date !== null;
        $this->end_date_precision = $story->end_date_precision ?? 'exact';
        $this->end_date = $story->end_date_precision === 'exact' ? $story->end_date?->toDateString() : null;
        $this->end_year = $story->end_date_precision === 'year' ? $story->end_date?->format('Y') : null;
    }

    public function submit(): void
    {
        Gate::authorize('suggest', $this->story);

        $currentYear = (int) now()->format('Y');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:70'],
            'body' => ['nullable', 'string', 'max:50000'],
            'start_date_precision' => ['required', 'in:exact,year'],
            'start_date' => ['required_if:start_date_precision,exact', 'nullable', 'date'],
            'start_year' => ['required_if:start_date_precision,year', 'nullable', 'integer', 'min:1000', 'max:'.$currentYear],
            'has_end_date' => ['boolean'],
            'end_date_precision' => ['required_if:has_end_date,true', 'in:exact,year'],
            'end_date' => ['nullable', 'date'],
            'end_year' => ['nullable', 'integer', 'min:1000', 'max:'.($currentYear + 1)],
        ]);

        $payload = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'start_date' => $validated['start_date_precision'] === 'year' ? "{$validated['start_year']}-01-01" : $validated['start_date'],
            'start_date_precision' => $validated['start_date_precision'],
            'end_date' => $validated['has_end_date'] ? ($validated['end_date_precision'] === 'year' ? "{$validated['end_year']}-01-01" : $validated['end_date']) : null,
            'end_date_precision' => $validated['has_end_date'] ? $validated['end_date_precision'] : null,
        ];

        app(SuggestionService::class)->submit($this->story, Auth::user(), $payload);

        $this->redirect(route('stories.show', $this->story), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Suggest an edit') }}</flux:heading>
    <flux:subheading>
        {{ __('Your changes go to this page\'s editors for review — they can merge, adjust, or decline them.') }}
    </flux:subheading>

    <form wire:submit="submit" class="mt-6 flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Title')" maxlength="70" required />
        <flux:editor wire:model="body" :label="__('Story')" toolbar="heading | bold italic underline strike | bullet ordered blockquote | link" class="**:data-[slot=content]:min-h-64" />

        <flux:separator />

        <flux:radio.group wire:model.live="start_date_precision" :label="__('When did this happen?')">
            <flux:radio value="exact" label="{{ __('Exact date') }}" />
            <flux:radio value="year" label="{{ __('Year only') }}" />
        </flux:radio.group>
        <div x-show="$wire.start_date_precision === 'exact'">
            <flux:input wire:model="start_date" type="date" :label="__('Start date')" />
        </div>
        <div x-show="$wire.start_date_precision === 'year'">
            <flux:input wire:model="start_year" type="number" :label="__('Year')" placeholder="1954" />
        </div>

        <flux:checkbox wire:model.live="has_end_date" :label="__('This spans a range of time (has an end date)')" />
        <div x-show="$wire.has_end_date" class="flex flex-col gap-6">
            <flux:radio.group wire:model.live="end_date_precision" :label="__('End date precision')">
                <flux:radio value="exact" label="{{ __('Exact date') }}" />
                <flux:radio value="year" label="{{ __('Year only') }}" />
            </flux:radio.group>
            <div x-show="$wire.end_date_precision === 'exact'">
                <flux:input wire:model="end_date" type="date" :label="__('End date')" />
            </div>
            <div x-show="$wire.end_date_precision === 'year'">
                <flux:input wire:model="end_year" type="number" :label="__('Year')" placeholder="1962" />
            </div>
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Submit suggestion') }}</flux:button>
            <flux:button :href="route('stories.show', $story)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
