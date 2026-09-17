<?php

use App\Models\Person;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    public function goToPerson(int $id): void
    {
        $this->redirect(route('people.show', $id), navigate: true);
    }
}; ?>

<div
    x-data
    x-on:keydown.window="
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            $flux.modal('global-search').show();
            setTimeout(() => $refs.searchInput.focus(), 50);
        }
    "
>
    <flux:modal name="global-search" variant="bare" class="w-full max-w-md">
        <flux:command>
            <flux:command.input x-ref="searchInput" :placeholder="__('Jump to a person… (⌘K)')" clearable />
            <flux:command.items>
                @foreach ($this->people as $person)
                    <flux:command.item wire:click="goToPerson({{ $person->id }})" wire:key="global-search-{{ $person->id }}">
                        {{ $person->fullName() }}
                    </flux:command.item>
                @endforeach
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
