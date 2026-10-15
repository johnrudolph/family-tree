{{--
    Expects an ancestor with x-data exposing `hovered` — see
    photo-pin-overlay.blade.php, its sibling partial.

    Expects: $photo (with `people` loaded, pivot x/y included), $canEdit,
    $nameClass (text/border color for this list — the lightbox sits on a
    dark background, the photo page doesn't).
--}}
@if ($photo->people->isNotEmpty())
    <div class="flex flex-wrap gap-2">
        @foreach ($photo->people as $person)
            <div
                wire:key="tag-{{ $person->id }}"
                x-on:mouseenter="hovered = {{ $person->id }}"
                x-on:mouseleave="hovered = null"
                x-bind:class="hovered === {{ $person->id }} ? 'border-blue-500' : 'border-transparent'"
                class="flex items-center gap-1 rounded-full border px-2 py-1 text-xs {{ $nameClass ?? '' }}"
            >
                <a href="{{ route('people.show', $person) }}" wire:navigate class="hover:underline">{{ $person->fullName() }}</a>
                @if ($canEdit)
                    <button
                        type="button"
                        wire:click="{{ $person->pivot->x !== null ? 'clearPersonPin' : 'togglePlacingPin' }}({{ $person->id }})"
                        class="opacity-60 hover:opacity-100"
                        title="{{ $person->pivot->x !== null ? __('Remove pin') : __('Place pin') }}"
                    >
                        @if ($person->pivot->x !== null)
                            <flux:icon.map-pin class="size-3" />
                        @else
                            <flux:icon.plus-circle class="size-3" />
                        @endif
                    </button>
                @endif
            </div>
        @endforeach
    </div>
@endif
