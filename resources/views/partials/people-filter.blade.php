<flux:pillbox wire:model.live="selectedPersonIds" multiple searchable :placeholder="__('Filter to specific people…')" class="sm:w-64">
    @foreach ($this->filterablePeople as $person)
        <flux:pillbox.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:pillbox.option>
    @endforeach
</flux:pillbox>
