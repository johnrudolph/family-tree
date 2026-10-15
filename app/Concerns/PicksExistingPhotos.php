<?php

namespace App\Concerns;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

/**
 * Shared "pick from the photo library, or upload new ones" behavior for any
 * Volt component that attaches photos to something (a story, eventually a
 * person). New uploads are gated to admins (see PhotoPolicy); picking
 * already-existing photos is not — that's just choosing a reference, not
 * creating anything.
 */
trait PicksExistingPhotos
{
    /** @var array<int, int> */
    public array $selectedPhotoIds = [];

    public string $photoSearch = '';

    /**
     * When set (e.g. to a marriage's two spouses), availablePhotos()
     * defaults to only photos tagged with every one of these people —
     * the photos most likely relevant — until showAllPhotos lifts it.
     *
     * @var array<int, int>
     */
    public array $photoPriorityPersonIds = [];

    public bool $showAllPhotos = false;

    #[Computed]
    public function availablePhotos(): Collection
    {
        return Photo::query()
            ->with('media')
            ->when($this->photoSearch, fn ($query) => $query->where('description', 'like', "%{$this->photoSearch}%"))
            ->when(
                $this->photoPriorityPersonIds !== [] && ! $this->showAllPhotos,
                fn ($query) => $query->whereHas(
                    'people',
                    fn ($q) => $q->whereIn('people.id', $this->photoPriorityPersonIds),
                    '>=',
                    count($this->photoPriorityPersonIds)
                )
            )
            ->latest()
            ->limit(60)
            ->get();
    }

    public function togglePhoto(int $photoId): void
    {
        if (in_array($photoId, $this->selectedPhotoIds, true)) {
            $this->selectedPhotoIds = array_values(array_diff($this->selectedPhotoIds, [$photoId]));

            return;
        }

        $this->selectedPhotoIds[] = $photoId;
    }
}
