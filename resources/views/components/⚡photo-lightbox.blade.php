<?php

use App\Concerns\EditsPhotoDetails;
use App\Models\Person;
use App\Models\Photo;
use App\Support\MediaUrl;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mounted once, globally, in layouts/app.blade.php — any page can open it by
 * dispatching `open-photo-lightbox` with a photo id and the ordered list of
 * ids to cycle through (that person's photos, that story's photos, the
 * photos index's current filtered results, ...). Edits save immediately as
 * fields change rather than needing an explicit "Save" — the whole point is
 * being able to rifle through a batch of untagged photos and tag each one
 * without breaking stride — with a brief toast confirming each save, since
 * there's no button press to otherwise signal it happened. Tagged-people
 * saves are isolated from description/date/location saves (see
 * EditsPhotoDetails) so a stale or incomplete date field can never silently
 * block a tag from persisting.
 */
new class extends Component {
    use EditsPhotoDetails;

    public ?int $photoId = null;

    /** @var array<int, int> */
    public array $contextIds = [];

    /**
     * @param  array<int, int>  $contextIds
     */
    #[On('open-photo-lightbox')]
    public function open(int $photoId, array $contextIds = []): void
    {
        $this->photoId = $photoId;
        $this->contextIds = $contextIds ?: [$photoId];
        $this->loadCurrentPhoto();
    }

    public function close(): void
    {
        $this->photoId = null;
        $this->contextIds = [];
    }

    public function next(): void
    {
        $this->advance(1);
    }

    public function previous(): void
    {
        $this->advance(-1);
    }

    private function advance(int $delta): void
    {
        $count = count($this->contextIds);

        if ($count < 2 || $this->photoId === null) {
            return;
        }

        $position = array_search($this->photoId, $this->contextIds, true);
        $position = $position === false ? 0 : $position;

        $this->photoId = $this->contextIds[($position + $delta + $count) % $count];
        $this->loadCurrentPhoto();
    }

    private function loadCurrentPhoto(): void
    {
        unset($this->photo);

        if ($this->photo) {
            $this->fillPhotoForm($this->photo);
        }
    }

    #[Computed]
    public function photo(): ?Photo
    {
        if (! $this->photoId) {
            return null;
        }

        return Photo::query()->with(['people', 'location'])->find($this->photoId);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->photo !== null && Gate::allows('update', $this->photo);
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    #[On('location-selected')]
    public function onLocationSelected(string $field, ?int $locationId): void
    {
        if ($field !== 'lightbox-photo') {
            return;
        }

        $this->location_id = $locationId;
        $this->autosaveDetails();
    }

    public function updatedDescription(): void
    {
        $this->autosaveDetails();
    }

    public function updatedHasDate(): void
    {
        $this->autosaveDetails();
    }

    public function updatedDatePrecision(): void
    {
        $this->autosaveDetails();
    }

    public function updatedDate(): void
    {
        $this->autosaveDetails();
    }

    public function updatedYear(): void
    {
        $this->autosaveDetails();
    }

    public function updatedPersonIds(): void
    {
        $this->autosaveTaggedPeople();
    }

    /**
     * Isolated from autosaveTaggedPeople() below — a stale or incomplete
     * date field (e.g. "year only" picked but no year typed yet) must
     * never block saving a tag, or vice versa.
     */
    private function autosaveDetails(): void
    {
        if (! $this->photo || ! $this->canEdit) {
            return;
        }

        $this->saveDescriptionDateAndLocation($this->photo);
        unset($this->photo);

        Flux::toast(variant: 'success', text: __('Saved.'), duration: 1500);
    }

    private function autosaveTaggedPeople(): void
    {
        if (! $this->photo || ! $this->canEdit) {
            return;
        }

        $this->saveTaggedPeople($this->photo);
        unset($this->photo);

        Flux::toast(variant: 'success', text: __('Saved.'), duration: 1500);
    }

    public function placePin(float $x, float $y): void
    {
        if (! $this->photo || ! $this->placingPinId || ! $this->canEdit) {
            return;
        }

        $this->setPersonPin($this->photo, $this->placingPinId, $x, $y);
        $this->placingPinId = null;
        unset($this->photo);
    }

    public function clearPersonPin(int $personId): void
    {
        if (! $this->photo || ! $this->canEdit) {
            return;
        }

        $this->removePersonPin($this->photo, $personId);
        unset($this->photo);
    }
}; ?>

<div>
    @if ($photoId && $this->photo)
        <div
            class="fixed inset-0 z-[80] flex flex-col bg-black/95"
            x-data="{ hovered: null, placing: $wire.entangle('placingPinId') }"
            x-on:keydown.window="
                if (['INPUT', 'TEXTAREA'].includes($event.target.tagName)) return;
                if ($event.key === 'ArrowLeft') $wire.previous();
                else if ($event.key === 'ArrowRight') $wire.next();
                else if ($event.key === 'Escape') $wire.close();
            "
        >
            <button type="button" wire:click="close" class="absolute top-4 right-4 z-10 rounded-full p-2 text-white/70 hover:text-white">
                <flux:icon.x-mark class="size-8" />
            </button>

            @if (count($contextIds) > 1)
                <button type="button" wire:click="previous" class="absolute top-1/2 left-2 z-10 -translate-y-1/2 rounded-full p-2 text-white/70 hover:text-white sm:left-4">
                    <flux:icon.chevron-left class="size-8" />
                </button>
                <button type="button" wire:click="next" class="absolute top-1/2 right-2 z-10 -translate-y-1/2 rounded-full p-2 text-white/70 hover:text-white sm:right-4">
                    <flux:icon.chevron-right class="size-8" />
                </button>
            @endif

            <div class="flex min-h-0 flex-1 items-center justify-center p-4 pb-2" wire:key="lightbox-image-{{ $photoId }}">
                @if ($this->photo->fileMedia())
                    @include('partials.photo-pin-overlay', [
                        'photo' => $this->photo,
                        'imageUrl' => MediaUrl::of($this->photo->fileMedia()),
                        'imageClass' => 'max-h-full max-w-full rounded object-contain',
                        'canEdit' => $this->canEdit,
                        'placingPinId' => $placingPinId,
                    ])
                @endif
            </div>

            @if (count($contextIds) > 1)
                <div class="text-center text-xs text-white/50">
                    {{ array_search($photoId, $contextIds, true) + 1 }} / {{ count($contextIds) }}
                </div>
            @endif

            <div class="mx-auto mt-2 w-full max-w-2xl shrink-0 rounded-t-xl bg-zinc-900 p-4 text-white" wire:key="lightbox-details-{{ $photoId }}">
                @if ($this->canEdit)
                    <div class="flex flex-col gap-3">
                        <flux:textarea wire:model.live.debounce.600ms="description" placeholder="{{ __('Add a description…') }}" rows="2" class="**:text-white **:placeholder:text-white/40" />

                        <flux:select variant="listbox" searchable multiple wire:model.live="person_ids" :placeholder="__('Who is in this photo?')" class="**:text-white">
                            @foreach ($this->people() as $person)
                                <flux:select.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="flex flex-wrap items-end gap-3">
                            <flux:checkbox wire:model.live="hasDate" :label="__('Date known')" />
                            @if ($hasDate)
                                <flux:radio.group wire:model.live="date_precision" variant="segmented" size="sm">
                                    <flux:radio value="exact" label="{{ __('Exact') }}" />
                                    <flux:radio value="year" label="{{ __('Year') }}" />
                                </flux:radio.group>
                                <div x-show="$wire.date_precision === 'exact'" class="min-w-32">
                                    <flux:input wire:model.live="date" type="date" size="sm" />
                                </div>
                                <div x-show="$wire.date_precision === 'year'" class="w-24">
                                    <flux:input wire:model.live="year" type="number" size="sm" placeholder="1954" />
                                </div>
                            @endif
                        </div>

                        <livewire:location-picker field="lightbox-photo" :location-id="$location_id" :label="__('Where was this taken?')" wire:key="lightbox-location-picker-{{ $photoId }}" />

                        @include('partials.photo-tag-list', ['photo' => $this->photo, 'canEdit' => true, 'nameClass' => 'border-transparent text-white bg-white/10'])
                    </div>
                @else
                    <div class="flex flex-col gap-2 text-sm text-white/80">
                        @if ($this->photo->description)
                            <p>{{ $this->photo->description }}</p>
                        @endif

                        @include('partials.photo-tag-list', ['photo' => $this->photo, 'canEdit' => false, 'nameClass' => 'border-transparent text-white/80 bg-white/10'])
                    </div>
                @endif

                <a href="{{ route('photos.show', $this->photo) }}" wire:navigate class="mt-3 inline-block text-xs text-white/40 hover:text-white/70">
                    {{ __('Open full page') }}
                </a>
            </div>
        </div>
    @endif
</div>
