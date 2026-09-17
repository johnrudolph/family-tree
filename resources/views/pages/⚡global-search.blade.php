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
    x-data="{
        focusSearchInput() {
            // Flux's own command/select internals sometimes reclaim focus
            // right after the dialog opens (e.g. for keyboard-nav setup), so
            // a single focus() call — even delayed — can lose the race.
            // Retry across a few animation frames until it actually sticks.
            let attempts = 0
            const tryFocus = () => {
                this.$refs.searchInput.focus()
                if (document.activeElement !== this.$refs.searchInput && attempts++ < 15) {
                    requestAnimationFrame(tryFocus)
                }
            }
            requestAnimationFrame(tryFocus)
        },
    }"
    x-on:keydown.window="
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            $flux.modal('global-search').show();
        }
    "
    x-on:modal-show.window="if ($event.detail.name === 'global-search') focusSearchInput()"
>
    <flux:modal name="global-search" variant="bare" class="w-full max-w-md">
        <flux:command>
            <flux:command.input x-ref="searchInput" autofocus :placeholder="__('Jump to a person… (⌘K)')" clearable />
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
