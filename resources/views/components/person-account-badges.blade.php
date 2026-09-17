@props(['person'])

<div class="flex flex-wrap items-center gap-1.5">
    @if ($person->hasAccount())
        <flux:badge size="sm" color="lime">{{ __('Has account') }}</flux:badge>
        @if ($person->isEditorOfOwnPage())
            <flux:badge size="sm" color="blue">{{ __('Editor') }}</flux:badge>
        @endif
    @else
        <flux:badge size="sm" color="zinc">{{ __('No account') }}</flux:badge>
    @endif
</div>
