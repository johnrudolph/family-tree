<?php

namespace App\Concerns;

use App\Models\Photo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;

/**
 * Shared description/date/location/tagged-people form state and save logic
 * for any Volt component that edits a Photo — the dedicated photo page and
 * the cross-app lightbox both need the exact same fields and validation.
 */
trait EditsPhotoDetails
{
    public string $description = '';

    public string $date_precision = 'exact';

    public ?string $date = null;

    public ?string $year = null;

    public bool $hasDate = false;

    public ?int $location_id = null;

    /** @var array<int, int> */
    public array $person_ids = [];

    public function fillPhotoForm(Photo $photo): void
    {
        $this->description = $photo->description ?? '';
        $this->hasDate = $photo->date !== null;
        $this->date_precision = $photo->date_precision ?? 'exact';
        $this->date = $photo->date_precision === 'exact' ? $photo->date?->toDateString() : null;
        $this->year = $photo->date_precision === 'year' ? $photo->date?->format('Y') : null;
        $this->location_id = $photo->location_id;
        $this->person_ids = $photo->people->pluck('id')->all();
    }

    public function savePhotoDetails(Photo $photo): void
    {
        Gate::authorize('update', $photo);

        $currentYear = (int) now()->format('Y');

        $validated = $this->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'hasDate' => ['boolean'],
            'date_precision' => ['required_if:hasDate,true', 'in:exact,year'],
            'date' => ['required_if:date_precision,exact', 'nullable', 'date'],
            'year' => ['required_if:date_precision,year', 'nullable', 'integer', 'min:1000', 'max:'.$currentYear],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'person_ids' => ['array'],
            'person_ids.*' => ['exists:people,id'],
        ]);

        $photo->update([
            'description' => $validated['description'] ?: null,
            'date' => $validated['hasDate'] ? ($validated['date_precision'] === 'year' ? "{$validated['year']}-01-01" : $validated['date']) : null,
            'date_precision' => $validated['hasDate'] ? $validated['date_precision'] : null,
            'location_id' => $validated['location_id'],
        ]);

        $this->syncPeopleSafely($photo, $validated['person_ids']);
    }

    /**
     * The lightbox autosaves on every change — selecting a tag, then
     * immediately clicking "next" before that request finishes, fires a
     * second overlapping save for the same photo. sync() reads the pivot
     * table and then writes based on that read; two near-simultaneous
     * requests can both read "not yet attached" and both try to insert the
     * same row, and the second one hits the unique constraint. Retrying
     * once is enough — the retry re-reads the now-current pivot state and
     * no longer tries to insert what the other request already committed.
     *
     * @param  array<int, int>  $personIds
     */
    private function syncPeopleSafely(Photo $photo, array $personIds): void
    {
        try {
            $photo->people()->sync($personIds);
        } catch (UniqueConstraintViolationException) {
            $photo->people()->sync($personIds);
        }
    }
}
