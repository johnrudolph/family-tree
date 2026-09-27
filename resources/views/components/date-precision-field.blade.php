@props(['label', 'dateModel', 'precisionModel', 'yearModel', 'yearPlaceholder' => '1954'])

<div class="flex flex-col gap-3">
    <flux:radio.group wire:model.live="{{ $precisionModel }}" :label="$label">
        <flux:radio value="exact" label="{{ __('Exact date') }}" />
        <flux:radio value="year" label="{{ __('Year only') }}" />
    </flux:radio.group>

    <div x-show="$wire.{{ $precisionModel }} === 'exact'">
        <flux:input wire:model="{{ $dateModel }}" type="date" />
    </div>
    <div x-show="$wire.{{ $precisionModel }} === 'year'">
        <flux:input wire:model="{{ $yearModel }}" type="number" :placeholder="$yearPlaceholder" />
    </div>
</div>
