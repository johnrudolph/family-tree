<?php

use App\Models\Person;
use App\Models\Story;
use App\Services\PageEditorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New story')] class extends Component {
    public string $title = '';

    public string $body = '';

    /** @var array<int, int> */
    public array $person_ids = [];

    public function mount(): void
    {
        Gate::authorize('create', Story::class);
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    public function save(): void
    {
        Gate::authorize('create', Story::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'person_ids' => ['array'],
            'person_ids.*' => ['exists:people,id'],
        ]);

        $story = Story::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'created_by' => Auth::id(),
        ]);

        $story->people()->sync($validated['person_ids']);

        app(PageEditorService::class)->grantOwner($story, Auth::user());

        $this->redirect(route('stories.show', $story), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('New story') }}</flux:heading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Title')" required />
        <flux:textarea wire:model="body" :label="__('Story (markdown supported)')" rows="12" />

        <flux:select variant="listbox" searchable multiple wire:model="person_ids" :label="__('Who is this story about?')" :placeholder="__('Search people…')">
            @foreach ($this->people() as $person)
                <flux:select.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Publish') }}</flux:button>
            <flux:button :href="route('stories.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
