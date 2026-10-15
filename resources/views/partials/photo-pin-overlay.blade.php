{{--
    Expects an ancestor with x-data exposing `hovered` and `placing` (an
    entangled $wire.placingPinId) — see photo-tag-list.blade.php, its
    sibling partial, and the lightbox/photo-show pages that supply that
    shared scope so hovering a pin here and hovering a name in the list
    below highlight each other.

    Expects: $photo (with `people` loaded, pivot x/y included), $imageUrl,
    $imageClass — must let the <img> shrink-wrap to its rendered size (e.g.
    "max-h-full max-w-full object-contain"), never a forced w-full/h-full,
    or the pin percentages below won't line up with the visible picture.
--}}
<div class="relative inline-block">
    <img
        src="{{ $imageUrl }}"
        class="block {{ $imageClass }}"
        x-bind:class="placing ? 'cursor-crosshair' : ''"
        x-on:click="
            if (! placing) return;
            const rect = $event.target.getBoundingClientRect();
            $wire.placePin((($event.clientX - rect.left) / rect.width) * 100, (($event.clientY - rect.top) / rect.height) * 100);
        "
        alt=""
    >

    @foreach ($photo->people as $person)
        @if ($person->pivot->x !== null)
            <button
                type="button"
                wire:key="pin-{{ $person->id }}"
                x-on:mouseenter="hovered = {{ $person->id }}"
                x-on:mouseleave="hovered = null"
                @if ($canEdit) wire:click.stop="togglePlacingPin({{ $person->id }})" @endif
                x-bind:class="hovered === {{ $person->id }} ? 'scale-150 bg-blue-500 border-white' : 'bg-white/90 border-blue-500'"
                class="absolute z-10 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 shadow transition-transform"
                style="left: {{ $person->pivot->x }}%; top: {{ $person->pivot->y }}%;"
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

@if ($canEdit && $placingPinId)
    <flux:text class="mt-2 text-xs text-amber-500">
        {{ __('Click on the photo to place a pin for :name.', ['name' => $photo->people->firstWhere('id', $placingPinId)?->fullName()]) }}
    </flux:text>
@endif
