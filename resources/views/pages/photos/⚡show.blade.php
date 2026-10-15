<?php

use App\Concerns\EditsPhotoDetails;
use App\Models\Person;
use App\Models\Photo;
use App\Services\PhotoDeletionService;
use App\Support\MediaUrl;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use EditsPhotoDetails;

    #[Locked]
    public Photo $photo;

    public function mount(Photo $photo): void
    {
        $this->photo = $photo;
        $this->fillPhotoForm($photo);
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Gate::allows('update', $this->photo);
    }

    #[Computed]
    public function canDelete(): bool
    {
        return Gate::allows('delete', $this->photo);
    }

    #[On('location-selected')]
    public function onLocationSelected(string $field, ?int $locationId): void
    {
        $this->location_id = $locationId;
    }

    public function save(): void
    {
        $this->savePhotoDetails($this->photo);

        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    public function placePin(float $x, float $y): void
    {
        if (! $this->placingPinId || ! $this->canEdit) {
            return;
        }

        $this->setPersonPin($this->photo, $this->placingPinId, $x, $y);
        $this->placingPinId = null;
    }

    public function clearPersonPin(int $personId): void
    {
        if (! $this->canEdit) {
            return;
        }

        $this->removePersonPin($this->photo, $personId);
    }

    public function deletePhoto(): void
    {
        Gate::authorize('delete', $this->photo);

        app(PhotoDeletionService::class)->delete($this->photo);

        Flux::toast(variant: 'success', text: __('Photo deleted.'));

        $this->redirect(route('photos.index'), navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->photo->description ?: __('Photo'));
    }
}; ?>

<section class="w-full max-w-2xl">
    <div class="flex items-start justify-between gap-4">
        <flux:heading level="1">{{ __('Photo') }}</flux:heading>
        @if ($this->canDelete)
            <flux:button wire:click="deletePhoto" wire:confirm="{{ __('Delete this photo? It will be removed from every story and person it\'s tagged to.') }}" variant="danger" size="sm">
                {{ __('Delete') }}
            </flux:button>
        @endif
    </div>

    <div x-data="{ hovered: null, placing: $wire.entangle('placingPinId') }">
        @if ($photo->fileMedia())
            @include('partials.photo-pin-overlay', [
                'photo' => $photo,
                'imageUrl' => MediaUrl::of($photo->fileMedia()),
                'imageClass' => 'max-h-[32rem] w-full rounded-lg object-contain bg-zinc-100 dark:bg-zinc-800',
                'containerClass' => 'mt-4',
                'canEdit' => $this->canEdit,
                'placingPinId' => $placingPinId,
            ])
        @endif

        <div class="mt-3">
            @include('partials.photo-tag-list', ['photo' => $photo, 'canEdit' => $this->canEdit, 'nameClass' => 'border-transparent bg-zinc-100 dark:bg-zinc-800'])
        </div>
    </div>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:textarea wire:model="description" :label="__('Description')" :disabled="! $this->canEdit" rows="3" />

        <flux:checkbox wire:model.live="hasDate" :label="__('Date known')" :disabled="! $this->canEdit" />
        @if ($hasDate)
            <flux:radio.group wire:model.live="date_precision" :label="__('Precision')" :disabled="! $this->canEdit">
                <flux:radio value="exact" label="{{ __('Exact date') }}" />
                <flux:radio value="year" label="{{ __('Year only') }}" />
            </flux:radio.group>
            <div x-show="$wire.date_precision === 'exact'">
                <flux:input wire:model="date" type="date" :label="__('Date')" :disabled="! $this->canEdit" />
            </div>
            <div x-show="$wire.date_precision === 'year'">
                <flux:input wire:model="year" type="number" :label="__('Year')" placeholder="1954" :disabled="! $this->canEdit" />
            </div>
        @endif

        <livewire:location-picker field="photo" :location-id="$location_id" :label="__('Where was this taken?')" wire:key="photo-location-picker" />

        <flux:select variant="listbox" searchable multiple wire:model="person_ids" :label="__('Who is in this photo?')" :placeholder="__('Search people…')" :disabled="! $this->canEdit">
            @foreach ($this->people() as $person)
                <flux:select.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->canEdit)
            <div>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        @endif
    </form>

    @if ($photo->stories->isNotEmpty())
        <div class="mt-8">
            <flux:heading level="2" size="sm">{{ __('Used in') }}</flux:heading>
            <div class="mt-2 flex flex-col gap-1">
                @foreach ($photo->stories as $story)
                    <a href="{{ route('stories.show', $story) }}" wire:navigate class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                        {{ $story->title }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</section>
