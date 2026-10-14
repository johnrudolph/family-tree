<div>
    <flux:input wire:model.live.debounce.300ms="photoSearch" :placeholder="__('Search photo descriptions…')" size="sm" class="mb-2" />

    <div class="grid max-h-80 grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6">
        @forelse ($this->availablePhotos as $photo)
            <button
                type="button"
                wire:click="togglePhoto({{ $photo->id }})"
                wire:key="pick-photo-{{ $photo->id }}"
                class="relative aspect-square overflow-hidden rounded {{ in_array($photo->id, $selectedPhotoIds, true) ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-zinc-800' : '' }}"
            >
                @if ($photo->fileMedia())
                    <img src="{{ \App\Support\MediaUrl::of($photo->fileMedia()) }}" class="h-full w-full object-cover" alt="">
                @endif
                @if (in_array($photo->id, $selectedPhotoIds, true))
                    <span class="absolute inset-0 flex items-center justify-center bg-blue-500/30">
                        <flux:icon.check class="size-6 text-white drop-shadow" />
                    </span>
                @endif
            </button>
        @empty
            <flux:text class="col-span-full text-sm text-zinc-500">{{ __('No photos yet.') }}</flux:text>
        @endforelse
    </div>
</div>
