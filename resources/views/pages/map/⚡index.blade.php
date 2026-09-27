<?php

use App\Support\MapSerializer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Map')] class extends Component {
    #[Computed]
    public function points(): array
    {
        return MapSerializer::points();
    }
}; ?>

<section class="flex w-full flex-col">
    <flux:heading level="1">{{ __('Map') }}</flux:heading>
    <flux:subheading>
        {{ __('Every birth, death, and story we know a location for.') }}
    </flux:subheading>

    <div
        wire:ignore
        x-data
        x-init="initMap($el, @js($this->points))"
        class="relative mt-4 h-[75vh] w-full overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"
    ></div>
</section>
