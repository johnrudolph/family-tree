<?php

use App\Models\Person;
use App\Services\PageEditorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add a person')] class extends Component {
    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $preferred_name = '';

    public bool $use_preferred_name_everywhere = false;

    public ?string $dob = null;

    public ?string $dod = null;

    public bool $is_living = true;

    public function mount(): void
    {
        Gate::authorize('create', Person::class);
    }

    public function save(): void
    {
        Gate::authorize('create', Person::class);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'use_preferred_name_everywhere' => ['boolean'],
            'dob' => ['nullable', 'date'],
            'dod' => ['nullable', 'date'],
            'is_living' => ['boolean'],
        ]);

        $person = Person::create([
            ...$validated,
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'] ?: null,
            'preferred_name' => $validated['preferred_name'] ?: null,
            'use_preferred_name_everywhere' => $validated['preferred_name'] && $validated['use_preferred_name_everywhere'],
            'created_by' => Auth::id(),
        ]);

        app(PageEditorService::class)->grantOwner($person, Auth::user());

        $this->redirect(route('people.show', $person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Add a person') }}</flux:heading>
    <flux:subheading>
        {{ __('For relatives who aren\'t on the tree yet — they don\'t need an account or email to be added. Invite them later from their page if they want to join.') }}
    </flux:subheading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:heading level="2" size="sm">{{ __('Name on birth certificate') }}</flux:heading>
        <flux:input wire:model="first_name" :label="__('First name')" required autofocus />
        <flux:input wire:model="middle_name" :label="__('Middle name')" />
        <flux:input wire:model="last_name" :label="__('Last name')" />

        <flux:separator />

        <flux:input wire:model.live="preferred_name" :label="__('Goes by')" :description="__('An informal name, if different — e.g. a nickname.')" />
        @if ($preferred_name !== '')
            <flux:checkbox wire:model="use_preferred_name_everywhere" :label="__('Use this everywhere')" :description="__('Show \':name\' instead of the full name on the tree, dropdowns, and pages.', ['name' => $preferred_name])" />
        @endif

        <flux:separator />

        <flux:input wire:model="dob" type="date" :label="__('Date of birth')" />
        <flux:checkbox wire:model.live="is_living" :label="__('Living')" />
        @unless ($is_living)
            <flux:input wire:model="dod" type="date" :label="__('Date of death (optional)')" />
        @endunless

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Add person') }}</flux:button>
            <flux:button :href="route('people.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
