<?php

use App\Support\FamilyTreeSerializer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Family Tree')] class extends Component {
    #[Computed]
    public function treeData(): array
    {
        return FamilyTreeSerializer::toChartData();
    }

    #[Computed]
    public function mainId(): ?int
    {
        return Auth::user()->person_id;
    }
}; ?>

<section class="w-full" wire:ignore
    x-data
    x-init="initFamilyTree($refs.chart, @js($this->treeData), { mainId: @js($this->mainId) })"
>
    <flux:heading level="1">{{ __('Family Tree') }}</flux:heading>
    <flux:subheading>{{ __('Click a card to open that person\'s page.') }}</flux:subheading>

    <div x-ref="chart" class="mt-4 h-[75vh] w-full rounded-lg border border-zinc-200 dark:border-zinc-700" id="family-tree-chart"></div>
</section>
