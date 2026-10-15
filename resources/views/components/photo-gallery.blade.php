@props(['photos'])

{{--
    Shared by stories and people: a grid of thumbnails where clicking any one
    opens the global photo lightbox (<livewire:photo-lightbox>, mounted once
    in layouts/app.blade.php), with left/right arrow keys and prev/next
    cycling through every other photo in this same gallery.

    `photos` is an array/Collection of ['id' => int, 'thumb' => url], in the
    order they should both display and be cycled through.
--}}
@php($ids = collect($photos)->pluck('id')->all())
@if (count($photos) > 0)
    <div {{ $attributes->class('grid grid-cols-2 gap-2 sm:grid-cols-4') }}>
        @foreach ($photos as $p)
            <button type="button" wire:click="$dispatch('open-photo-lightbox', { photoId: {{ $p['id'] }}, contextIds: @js($ids) })">
                <img src="{{ $p['thumb'] }}" class="aspect-square rounded-lg object-cover" alt="">
            </button>
        @endforeach
    </div>
@endif
