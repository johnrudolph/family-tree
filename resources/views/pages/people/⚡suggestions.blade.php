<?php

use App\Models\Person;
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
    public Person $person;

    public ?int $editingSuggestionId = null;

    /** @var array<string, mixed> */
    public array $editPayload = [];

    public function mount(Person $person): void
    {
        Gate::authorize('update', $person);

        $this->person = $person;
    }

    public function editSuggestion(int $suggestionId): void
    {
        $suggestion = $this->person->suggestions()->findOrFail($suggestionId);
        $this->editingSuggestionId = $suggestionId;
        $this->editPayload = $suggestion->payload;
    }

    public function mergeWithChanges(): void
    {
        Gate::authorize('update', $this->person);

        $suggestion = $this->person->suggestions()->findOrFail($this->editingSuggestionId);
        app(SuggestionService::class)->merge($suggestion, Auth::user(), $this->editPayload);

        $this->editingSuggestionId = null;
        Flux::toast(variant: 'success', text: __('Suggestion merged with your changes.'));
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'last_name' => 'Last name',
            'preferred_name' => 'Goes by',
            'use_preferred_name_everywhere' => 'Use "goes by" name everywhere',
            'dob' => 'Date of birth',
            'dod' => 'Date of death',
            'is_living' => 'Living',
            'bio' => 'Bio',
            default => $field,
        };
    }

    public function diffsFor(int $suggestionId): array
    {
        $suggestion = $this->person->suggestions()->findOrFail($suggestionId);

        return collect($suggestion->payload)
            ->map(fn ($new, $field) => [
                'label' => $this->fieldLabel($field),
                'html' => SuggestionDiffer::fieldDiffHtml($this->person->{$field}, $new),
            ])
            ->filter(fn ($diff) => $diff['html'] !== '')
            ->values()
            ->all();
    }

    public function merge(int $suggestionId): void
    {
        Gate::authorize('update', $this->person);

        $suggestion = $this->person->suggestions()->findOrFail($suggestionId);
        app(SuggestionService::class)->merge($suggestion, Auth::user());

        Flux::toast(variant: 'success', text: __('Suggestion merged.'));
    }

    public function reject(int $suggestionId): void
    {
        Gate::authorize('update', $this->person);

        $suggestion = $this->person->suggestions()->findOrFail($suggestionId);
        app(SuggestionService::class)->reject($suggestion, Auth::user());

        Flux::toast(variant: 'success', text: __('Suggestion declined.'));
    }
}; ?>

<section class="w-full max-w-2xl">
    @include('partials.diff-styles')

    <flux:heading level="1">{{ __('Suggestions for') }} {{ $person->fullName() }}</flux:heading>

    <div class="mt-6 space-y-6">
        @forelse ($person->pendingSuggestions as $suggestion)
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
                            @if ($field === 'bio')
                                <flux:textarea wire:model="editPayload.{{ $field }}" :label="$this->fieldLabel($field)" rows="6" />
                            @elseif ($field === 'is_living')
                                <flux:checkbox wire:model="editPayload.{{ $field }}" :label="$this->fieldLabel($field)" />
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
