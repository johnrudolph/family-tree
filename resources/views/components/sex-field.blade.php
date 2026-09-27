@props(['model' => 'sex', 'label' => null])

<flux:radio.group wire:model="{{ $model }}" :label="$label ?? __('Sex')">
    <flux:radio value="male" label="{{ __('Male') }}" />
    <flux:radio value="female" label="{{ __('Female') }}" />
</flux:radio.group>
