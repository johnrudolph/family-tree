@props(['urls'])

{{-- Shared by stories and people: a grid of thumbnails where clicking any one
     opens the full-screen lightbox (resources/js/lightbox.js) with the whole
     set, starting on the photo that was clicked. --}}
@if ($urls)
    <div {{ $attributes->class('grid grid-cols-2 gap-2 sm:grid-cols-4') }}>
        @foreach ($urls as $i => $url)
            <button type="button" onclick="openLightbox(@js($urls), {{ $i }})">
                <img src="{{ $url }}" class="aspect-square rounded-lg object-cover" alt="">
            </button>
        @endforeach
    </div>
@endif
