<?php

use App\Models\Person;
use App\Services\SuggestionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Suggest an edit')] class extends Component {
    #[Locked]
    public Person $person;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $preferred_name = '';

    public ?string $dob = null;

    public ?string $dod = null;

    public bool $is_living = true;

    public string $bio = '';

    public function mount(Person $person): void
    {
        Gate::authorize('suggest', $person);

        $this->person = $person;
        $this->first_name = $person->first_name;
        $this->middle_name = $person->middle_name ?? '';
        $this->last_name = $person->last_name ?? '';
        $this->preferred_name = $person->preferred_name ?? '';
        $this->dob = $person->dob?->toDateString();
        $this->dod = $person->dod?->toDateString();
        $this->is_living = $person->is_living;
        $this->bio = $person->bio ?? '';
    }

    public function submit(): void
    {
        Gate::authorize('suggest', $this->person);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'dod' => ['nullable', 'date'],
            'is_living' => ['boolean'],
            'bio' => ['nullable', 'string', 'max:20000'],
        ]);

        $payload = [
            ...$validated,
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'preferred_name' => $validated['preferred_name'] ?: null,
        ];

        app(SuggestionService::class)->submit($this->person, Auth::user(), $payload);

        $this->redirect(route('people.show', $this->person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Suggest an edit') }}</flux:heading>
    <flux:subheading>
        {{ __('Your changes go to this page\'s editors for review — they can merge, adjust, or decline them.') }}
    </flux:subheading>

    <form wire:submit="submit" class="mt-6 flex flex-col gap-6">
        <flux:input wire:model="first_name" :label="__('First name')" required />
        <flux:input wire:model="middle_name" :label="__('Middle name')" />
        <flux:input wire:model="last_name" :label="__('Last name')" />
        <flux:input wire:model="preferred_name" :label="__('Preferred name')" />
        <flux:input wire:model="dob" type="date" :label="__('Date of birth')" />
        <flux:checkbox wire:model.live="is_living" :label="__('Living')" />
        @unless ($is_living)
            <flux:input wire:model="dod" type="date" :label="__('Date of death')" />
        @endunless
        <flux:textarea wire:model="bio" :label="__('Bio (markdown supported)')" rows="10" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Submit suggestion') }}</flux:button>
            <flux:button :href="route('people.show', $person)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
