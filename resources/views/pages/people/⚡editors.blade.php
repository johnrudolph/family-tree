<?php

use App\Models\Person;
use App\Models\User;
use App\Services\PageEditorService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage editors')] class extends Component {
    #[Locked]
    public Person $person;

    public ?int $newEditorUserId = null;

    public function mount(Person $person): void
    {
        Gate::authorize('update', $person);

        $this->person = $person;
    }

    #[Computed]
    public function editors()
    {
        return $this->person->pageEditors()->with('user')->orderBy('role')->get();
    }

    #[Computed]
    public function candidateUsers()
    {
        $existingIds = $this->person->pageEditors()->pluck('user_id');

        return User::query()->whereNotIn('id', $existingIds)->orderBy('name')->get();
    }

    public function addEditor(): void
    {
        Gate::authorize('update', $this->person);

        $validated = $this->validate(['newEditorUserId' => ['required', 'exists:users,id']]);

        $user = User::findOrFail($validated['newEditorUserId']);
        app(PageEditorService::class)->addCoEditor($this->person, $user);

        $this->reset('newEditorUserId');
        unset($this->editors, $this->candidateUsers);

        Flux::toast(variant: 'success', text: __('Editor added.'));
    }

    public function removeEditor(int $userId): void
    {
        Gate::authorize('update', $this->person);

        try {
            app(PageEditorService::class)->removeEditor($this->person, User::findOrFail($userId));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->editors, $this->candidateUsers);

        Flux::toast(variant: 'success', text: __('Editor removed.'));
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Editors for') }} {{ $person->fullName() }}</flux:heading>
    <flux:subheading>
        {{ __('Editors can update this page directly. Everyone else can only suggest changes for an editor to review.') }}
    </flux:subheading>

    <div class="mt-6 space-y-2">
        @foreach ($this->editors as $editor)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="editor-{{ $editor->id }}">
                <div>
                    <flux:text>{{ $editor->user->name }}</flux:text>
                    <flux:text class="text-xs text-zinc-500">{{ $editor->role === 'owner' ? __('Owner') : __('Co-editor') }}</flux:text>
                </div>
                <flux:button wire:click="removeEditor({{ $editor->user_id }})" size="sm" variant="ghost" icon="x-mark" />
            </div>
        @endforeach
    </div>

    <flux:separator class="my-8" />

    <flux:heading level="2">{{ __('Add a co-editor') }}</flux:heading>

    <form wire:submit="addEditor" class="mt-4 flex flex-col gap-4">
        <flux:select variant="combobox" wire:model="newEditorUserId" :label="__('Member')" :placeholder="__('Search members…')" clearable>
            @foreach ($this->candidateUsers as $candidate)
                <flux:select.option value="{{ $candidate->id }}">{{ $candidate->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div>
            <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
        </div>
    </form>
</section>
