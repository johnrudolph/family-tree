@if ($this->canFilterToDirectRelatives)
    <flux:checkbox wire:model.live="directRelativesOnly" :label="__('Only show my direct relatives')" />
@endif
