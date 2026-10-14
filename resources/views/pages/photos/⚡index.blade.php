<?php

use App\Models\Photo;
use App\Support\MediaUrl;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Photos')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function canUpload(): bool
    {
        return Gate::allows('create', Photo::class);
    }

    #[Computed]
    public function photos()
    {
        return Photo::query()
            ->with(['media', 'people'])
            ->when($this->search, fn ($query) => $query->where('description', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(24);
    }

    public function upload(): void
    {
        Gate::authorize('create', Photo::class);

        $validated = $this->validate([
            'newPhotos.*' => ['image', 'max:20480'],
        ]);

        foreach ($this->newPhotos as $uploaded) {
            $photo = Photo::create(['uploaded_by' => Auth::id()]);
            $photo->addMedia($uploaded->getRealPath())
                ->usingFileName($uploaded->getClientOriginalName())
                ->toMediaCollection('file');
        }

        $this->reset('newPhotos');
        unset($this->photos);

        Flux::toast(variant: 'success', text: __('Photos uploaded.'));
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <flux:heading level="1">{{ __('Photos') }}</flux:heading>
    </div>
    <flux:subheading>{{ __('Every standalone photo in the family archive — tag people, pick a date and location, and reuse the same photo across stories.') }}</flux:subheading>

    @if ($this->canUpload)
        <div class="mt-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <form wire:submit="upload" class="flex flex-col gap-3 sm:flex-row sm:items-end" x-data x-on:livewire-upload-error="$flux.toast(@js(__('Photo upload failed — try smaller images, or check your connection and try again.')), { variant: 'danger', duration: 8000 })">
                <div class="flex-1">
                    <flux:input type="file" wire:model="newPhotos" multiple accept="image/*" :label="__('Upload photos')" />
                </div>
                <flux:button type="submit" variant="primary">{{ __('Upload') }}</flux:button>
            </form>
        </div>
    @endif

    <div class="mt-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search descriptions…')" class="max-w-sm" />
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @forelse ($this->photos as $photo)
            <a href="{{ route('photos.show', $photo) }}" wire:navigate class="group relative aspect-square overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                @if ($photo->fileMedia())
                    <img src="{{ MediaUrl::of($photo->fileMedia()) }}" class="h-full w-full object-cover transition group-hover:opacity-90" alt="">
                @endif
                @if ($photo->people->isNotEmpty())
                    <span class="absolute right-1 bottom-1 left-1 truncate rounded bg-black/60 px-1.5 py-0.5 text-xs text-white">
                        {{ $photo->people->map->fullName()->join(', ') }}
                    </span>
                @endif
            </a>
        @empty
            <flux:text class="col-span-full text-zinc-500">{{ __('No photos yet.') }}</flux:text>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->photos->links() }}
    </div>
</section>
