{{--
    Expects an ancestor with x-data exposing `hovered` and `placing` — see
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
                    @if ($person->pivot->x !== null)
                        <button
                            type="button"
                            wire:click="clearPersonPin({{ $person->id }})"
                            class="flex items-center gap-0.5 rounded-full bg-blue-500/10 px-1.5 py-0.5 text-blue-600 hover:bg-blue-500/20 dark:text-blue-400"
                            title="{{ __('Remove pin') }}"
                        >
                            <flux:icon.map-pin class="size-3" />
                        </button>
                    @else
                        <button
                            type="button"
                            x-bind:class="placing === {{ $person->id }} ? 'bg-amber-500 text-white' : 'bg-zinc-200 text-zinc-700 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600'"
                            wire:click="togglePlacingPin({{ $person->id }})"
                            class="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        >
                            <flux:icon.plus-circle class="size-3" />
                            {{ __('Pin') }}
                        </button>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
@endif
