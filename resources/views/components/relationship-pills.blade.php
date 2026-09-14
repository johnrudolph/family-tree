@props(['label', 'property', 'candidates', 'selectedIds', 'hint' => null])

@php
    $selected = $candidates->whereIn('id', $selectedIds);
@endphp

@if ($selected->isNotEmpty())
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950">
        <flux:text class="font-medium text-blue-900 dark:text-blue-200">💡 {{ $label }}</flux:text>
        @if ($hint)
            <flux:text class="text-xs text-blue-700 dark:text-blue-400">{{ $hint }}</flux:text>
        @endif
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($selected as $item)
                <span
                    wire:key="{{ $property }}-pill-{{ $item->id }}"
                    class="inline-flex items-center gap-1 rounded-full bg-blue-200 py-0.5 pl-3 pr-1 text-sm text-blue-900 dark:bg-blue-800 dark:text-blue-100"
                >
                    {{ $item->fullName() }}
                    <button
                        type="button"
                        wire:click="removeSuggestion('{{ $property }}', {{ $item->id }})"
                        class="rounded-full p-0.5 text-blue-700 hover:bg-blue-300 hover:text-blue-950 dark:text-blue-300 dark:hover:bg-blue-700 dark:hover:text-white"
                        aria-label="{{ __('Remove') }}"
                    >
                        <flux:icon.x-mark class="size-3" />
                    </button>
                </span>
            @endforeach
        </div>
    </div>
@endif
