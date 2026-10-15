@props(['urls', 'fullUrls' => null])

{{-- Shared by stories and people: a grid of thumbnails where clicking any one
     opens the full-screen lightbox (resources/js/lightbox.js) with the whole
     set, starting on the photo that was clicked. `urls` (thumbnails) is what
     actually renders in the grid; `fullUrls` (same order, defaults to `urls`
     if the caller has no separate full-resolution set) is what the lightbox
     opens to — the grid shouldn't have to load full-resolution originals
     just to show a row of small squares. --}}
@php($lightboxUrls = $fullUrls ?? $urls)
@if ($urls)
    <div {{ $attributes->class('grid grid-cols-2 gap-2 sm:grid-cols-4') }}>
        @foreach ($urls as $i => $url)
            <button type="button" onclick="openLightbox(@js($lightboxUrls), {{ $i }})">
                <img src="{{ $url }}" class="aspect-square rounded-lg object-cover" alt="">
            </button>
        @endforeach
    </div>
@endif
