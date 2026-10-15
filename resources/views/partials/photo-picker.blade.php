<div>
    <flux:input wire:model.live.debounce.300ms="photoSearch" :placeholder="__('Search photo descriptions…')" size="sm" class="mb-2" />

    @if ($photoPriorityPersonIds !== [])
        <div class="mb-2 flex items-center justify-between">
            <flux:text class="text-xs text-zinc-500">
                {{ $showAllPhotos ? __('Showing all photos.') : __('Showing photos these two are already tagged in.') }}
            </flux:text>
            <flux:button wire:click="$set('showAllPhotos', {{ $showAllPhotos ? 'false' : 'true' }})" size="sm" variant="ghost">
                {{ $showAllPhotos ? __('Just them') : __('Show all photos') }}
            </flux:button>
        </div>
    @endif

    <div class="grid max-h-80 grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6">
        @forelse ($this->availablePhotos as $photo)
            <button
                type="button"
                wire:click="togglePhoto({{ $photo->id }})"
                wire:key="pick-photo-{{ $photo->id }}"
                class="relative aspect-square overflow-hidden rounded {{ in_array($photo->id, $selectedPhotoIds, true) ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-zinc-800' : '' }}"
            >
                @if ($photo->fileMedia())
                    <img src="{{ \App\Support\MediaUrl::of($photo->fileMedia(), conversion: 'thumb') }}" class="h-full w-full object-cover" alt="">
                @endif
                @if (in_array($photo->id, $selectedPhotoIds, true))
                    <span class="absolute inset-0 flex items-center justify-center bg-blue-500/30">
                        <flux:icon.check class="size-6 text-white drop-shadow" />
                    </span>
                @endif
            </button>
        @empty
            @if ($photoPriorityPersonIds !== [] && ! $showAllPhotos)
                <flux:text class="col-span-full text-sm text-zinc-500">
                    {{ __('No photos of just the two of them yet.') }}
                    <flux:link wire:click="$set('showAllPhotos', true)" class="cursor-pointer text-sm">{{ __('Show all photos') }}</flux:link>
                </flux:text>
            @else
                <flux:text class="col-span-full text-sm text-zinc-500">{{ __('No photos yet.') }}</flux:text>
            @endif
        @endforelse
    </div>
</div>
