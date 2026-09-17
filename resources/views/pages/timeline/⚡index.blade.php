<?php

use App\Support\TimelineSerializer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Timeline')] class extends Component {
    #[Computed]
    public function events(): array
    {
        return TimelineSerializer::events();
    }
}; ?>

<section class="flex w-full flex-col">
    <div class="flex items-center justify-between">
        <flux:heading level="1">{{ __('Timeline') }}</flux:heading>

        <div class="flex items-center gap-2">
            <flux:icon.magnifying-glass-minus class="size-4 text-zinc-400" />
            <input
                type="range"
                data-timeline-zoom-meter
                min="1"
                max="400"
                value="1"
                step="1"
                class="h-1.5 w-40 cursor-pointer appearance-none rounded-full bg-zinc-200 accent-blue-500 dark:bg-zinc-700"
            />
            <flux:icon.magnifying-glass-plus class="size-4 text-zinc-400" />
        </div>
    </div>

    <div
        wire:ignore
        x-data
        x-init="initTimeline($el, @js($this->events))"
        class="relative mt-4 h-[70vh] w-full overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
    >
        <svg class="timeline-svg absolute inset-0 block">
            <line class="timeline-baseline stroke-zinc-300 dark:stroke-zinc-600" stroke-width="2" />
            <line class="timeline-today stroke-blue-400" stroke-width="1" stroke-dasharray="4 3" />
            <g class="timeline-gridlines"></g>
            <g class="timeline-spans"></g>
            <g class="timeline-dots"></g>
        </svg>

        <div class="timeline-cards pointer-events-none absolute inset-0"></div>

        <div data-timeline-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
            <div class="max-h-[85vh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl dark:bg-zinc-800">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <flux:heading level="2" data-timeline-modal-title></flux:heading>
                        <flux:text data-timeline-modal-date class="text-zinc-500"></flux:text>
                    </div>
                    <button type="button" data-timeline-modal-close class="shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>
                <img data-timeline-modal-image class="mt-4 hidden max-h-80 w-full rounded-lg object-cover" alt="">
                <div data-timeline-modal-body class="prose prose-zinc dark:prose-invert mt-4 max-w-none"></div>
            </div>
        </div>
    </div>
</section>
