<?php

use App\Models\Person;
use App\Models\Story;
use App\Services\RevisionService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit story')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Story $story;

    public string $title = '';

    public string $body = '';

    /** @var array<int, int> */
    public array $person_ids = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    public function mount(Story $story): void
    {
        Gate::authorize('update', $story);

        $this->story = $story;
        $this->title = $story->title;
        $this->body = $story->body ?? '';
        $this->person_ids = $story->people->pluck('id')->all();
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    public function save(): void
    {
        Gate::authorize('update', $this->story);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'person_ids' => ['array'],
            'person_ids.*' => ['exists:people,id'],
            'newPhotos.*' => ['image', 'max:5120'],
        ]);

        $data = [
            'title' => $validated['title'],
            'body' => $validated['body'],
        ];

        $this->story->update($data);
        $this->story->people()->sync($validated['person_ids']);

        foreach ($this->newPhotos as $photo) {
            $this->story->addMedia($photo->getRealPath())
                ->usingFileName($photo->getClientOriginalName())
                ->toMediaCollection('gallery');
        }

        app(RevisionService::class)->record($this->story, Auth::user(), $data);

        Flux::toast(variant: 'success', text: __('Saved.'));

        $this->redirect(route('stories.show', $this->story), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Edit story') }}</flux:heading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Title')" required />
        <flux:textarea wire:model="body" :label="__('Story (markdown supported)')" rows="12" />

        <flux:select variant="listbox" searchable multiple wire:model="person_ids" :label="__('Who is this story about?')" :placeholder="__('Search people…')">
            @foreach ($this->people() as $person)
                <flux:select.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($story->galleryMedia()->isNotEmpty())
            <div class="grid grid-cols-4 gap-2">
                @foreach ($story->galleryMedia() as $media)
                    <img src="{{ $media->getTemporaryUrl(now()->addHour()) }}" class="aspect-square rounded object-cover" alt="">
                @endforeach
            </div>
        @endif

        <flux:input type="file" wire:model="newPhotos" multiple accept="image/*" :label="__('Add photos')" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button :href="route('stories.show', $story)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
