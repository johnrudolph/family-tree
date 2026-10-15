<?php

use App\Models\Photo;
use App\Support\MediaUrl;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Photos')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';

    public bool $untaggedOnly = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUntaggedOnly(): void
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
        return $this->filteredQuery()->with(['media', 'people'])->paginate(24);
    }

    /**
     * Every photo matching the current filters, not just the current page —
     * so the lightbox can flip through the whole filtered set (e.g. every
     * untagged photo) without being boxed in by pagination.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function allFilteredPhotoIds(): array
    {
        return $this->filteredQuery()->pluck('id')->all();
    }

    private function filteredQuery()
    {
        return Photo::query()
            ->when($this->search, fn ($query) => $query->where('description', 'like', "%{$this->search}%"))
            ->when($this->untaggedOnly, fn ($query) => $query->doesntHave('people'))
            ->latest();
    }

    /**
     * Each file in $newPhotos finishes its own upload independently (Livewire
     * chunks, previews, and tracks progress per-file natively) — as soon as
     * one lands here, turn it into a real Photo immediately rather than
     * waiting for the whole batch, so a single bad file or a closed tab
     * never loses photos that already finished uploading.
     */
    public function updatedNewPhotos(): void
    {
        Gate::authorize('create', Photo::class);

        $failures = 0;

        foreach ($this->newPhotos as $uploaded) {
            $validator = Validator::make(['photo' => $uploaded], ['photo' => ['image', 'max:20480']]);

            if ($validator->fails()) {
                $failures++;

                continue;
            }

            $photo = Photo::create(['uploaded_by' => Auth::id()]);
            $photo->addMedia($uploaded->getRealPath())
                ->usingFileName($uploaded->getClientOriginalName())
                ->toMediaCollection('file');
        }

        $this->reset('newPhotos');
        unset($this->photos);

        if ($failures > 0) {
            Flux::toast(variant: 'danger', text: trans_choice('1 photo was too large or not a valid image and was skipped.|:count photos were too large or not valid images and were skipped.', $failures, ['count' => $failures]));
        }
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <flux:heading level="1">{{ __('Photos') }}</flux:heading>
    </div>
    <flux:subheading>{{ __('Every standalone photo in the family archive — tag people, pick a date and location, and reuse the same photo across stories.') }}</flux:subheading>

    @if ($this->canUpload)
        <div class="mt-4">
            @include('partials.photo-upload-dropzone', ['model' => 'newPhotos'])
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search descriptions…')" class="max-w-sm" />
        <flux:checkbox wire:model.live="untaggedOnly" :label="__('No tags only')" :description="__('Find photos that still need people tagged')" />
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @forelse ($this->photos as $photo)
            <button type="button" wire:click="$dispatch('open-photo-lightbox', { photoId: {{ $photo->id }}, contextIds: @js($this->allFilteredPhotoIds) })" class="group relative aspect-square overflow-hidden rounded-lg bg-zinc-100 text-left dark:bg-zinc-800">
                @if ($photo->fileMedia())
                    <img src="{{ MediaUrl::of($photo->fileMedia(), conversion: 'thumb') }}" class="h-full w-full object-cover transition group-hover:opacity-90" alt="">
                @endif
                @if ($photo->people->isNotEmpty())
                    <span class="absolute right-1 bottom-1 left-1 truncate rounded bg-black/60 px-1.5 py-0.5 text-xs text-white">
                        {{ $photo->people->map->fullName()->join(', ') }}
                    </span>
                @endif
            </button>
        @empty
            <flux:text class="col-span-full text-zinc-500">{{ __('No photos yet.') }}</flux:text>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->photos->links() }}
    </div>
</section>
