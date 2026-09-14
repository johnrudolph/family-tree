<?php

use App\Models\Story;
use App\Services\SuggestionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Suggest an edit')] class extends Component {
    #[Locked]
    public Story $story;

    public string $title = '';

    public string $body = '';

    public function mount(Story $story): void
    {
        Gate::authorize('suggest', $story);

        $this->story = $story;
        $this->title = $story->title;
        $this->body = $story->body ?? '';
    }

    public function submit(): void
    {
        Gate::authorize('suggest', $this->story);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
        ]);

        app(SuggestionService::class)->submit($this->story, Auth::user(), $validated);

        $this->redirect(route('stories.show', $this->story), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Suggest an edit') }}</flux:heading>
    <flux:subheading>
        {{ __('Your changes go to this page\'s editors for review — they can merge, adjust, or decline them.') }}
    </flux:subheading>

    <form wire:submit="submit" class="mt-6 flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Title')" required />
        <flux:textarea wire:model="body" :label="__('Story (markdown supported)')" rows="12" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Submit suggestion') }}</flux:button>
            <flux:button :href="route('stories.show', $story)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
