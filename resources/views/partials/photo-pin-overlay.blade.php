{{--
    Expects an ancestor with x-data exposing `hovered` and `placing` (an
    entangled $wire.placingPinId) — see photo-tag-list.blade.php, its
    sibling partial, and the lightbox/photo-show pages that supply that
    shared scope so hovering a pin here and hovering a name in the list
    below highlight each other.

    Pins are hidden until the photo is hovered (or a pin's name is hovered
    in the list below, or a pin is actively being placed) — a photo full of
    permanently-visible dots gets noisy fast.

    Expects: $photo (with `people` loaded, pivot x/y included), $imageUrl,
    $imageClass (any sizing that keeps the image inside its box — object-
    contain, a max-height, etc), $containerClass (sizing/layout for THIS
    partial's own root element — e.g. a flex-centering box on the lightbox,
    just a block div elsewhere). $wireKey is optional, for callers that key
    this block on the photo id.

    $containerClass has to give this root element a size derived from ITS
    OWN parent (height/width percentages, flex-grow, a normal block fill),
    never from its content — an auto/shrink-sized wrapper around a child
    with a percentage max-width famously resolves that percentage against
    the image's own intrinsic pixel size instead, rendering it at full
    native resolution. Pin positions are computed in JS from the <img>'s
    own rendered box either way (see resources/js/photo-pins.js), so this
    element's exact size relative to the image doesn't otherwise matter.
--}}
<div
    @if (! empty($wireKey)) wire:key="{{ $wireKey }}" @endif
    class="relative {{ $containerClass }}"
    x-data="{ resizeTick: 0, hoveringPhoto: false }"
    x-on:resize.window="resizeTick++"
    x-on:mouseenter="hoveringPhoto = true"
    x-on:mouseleave="hoveringPhoto = false"
    x-init="$nextTick(() => { if ($refs.pinImage?.complete) resizeTick++ })"
>
    <img
        x-ref="pinImage"
        src="{{ $imageUrl }}"
        class="{{ $imageClass }}"
        x-bind:class="placing ? 'ring-4 ring-amber-400 cursor-crosshair' : ''"
        x-on:load="resizeTick++"
        x-on:click="
            if (! placing) return;
            const { x, y } = photoPinCoordinatesFromClick($event);
            $wire.placePin(x, y);
        "
        alt=""
    >

    <div
        x-show="placing"
        x-cloak
        class="pointer-events-none absolute inset-x-0 top-0 flex justify-center p-2"
    >
        <span class="rounded-full bg-amber-500 px-3 py-1 text-xs font-medium text-white shadow">
            {{ __('Click on the photo to place a pin for :name.', ['name' => $photo->people->firstWhere('id', $placingPinId)?->fullName()]) }}
        </span>
    </div>

    @foreach ($photo->people as $person)
        @if ($person->pivot->x !== null)
            <button
                type="button"
                wire:key="pin-{{ $person->id }}"
                x-on:mouseenter="hovered = {{ $person->id }}"
                x-on:mouseleave="hovered = null"
                @if ($canEdit) wire:click.stop="togglePlacingPin({{ $person->id }})" @endif
                x-bind:style="resizeTick >= 0 ? photoPinStyle($refs.pinImage, {{ $person->pivot->x }}, {{ $person->pivot->y }}) : ''"
                x-bind:class="(hoveringPhoto || placing || hovered === {{ $person->id }})
                    ? (hovered === {{ $person->id }} ? 'opacity-100 scale-150 bg-blue-500 border-white' : 'opacity-100 bg-white/90 border-blue-500')
                    : 'opacity-0 pointer-events-none bg-white/90 border-blue-500'"
                class="absolute z-10 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 shadow transition-all"
            >
                <span
                    x-show="hovered === {{ $person->id }}"
                    x-cloak
                    class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 rounded bg-black/80 px-2 py-0.5 text-xs whitespace-nowrap text-white"
                >{{ $person->fullName() }}</span>
            </button>
        @endif
    @endforeach
</div>
