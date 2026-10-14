<?php

namespace App\Models;

use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A standalone photo — it can exist with no person tagged and no story
 * attached, be tagged to any number of people (e.g. a group photo), and be
 * referenced by any number of stories. Stories and people never own a
 * photo's file, only reference it, so deleting either only removes the
 * reference, never the photo itself.
 *
 * @property int $id
 * @property int|null $uploaded_by
 * @property int|null $location_id
 * @property string|null $description
 * @property Carbon|null $date
 * @property string|null $date_precision
 * @property-read User|null $uploader
 * @property-read Location|null $location
 * @property-read Collection<int, Person> $people
 * @property-read Collection<int, Story> $stories
 */
#[Fillable(['uploaded_by', 'location_id', 'description', 'date', 'date_precision'])]
class Photo extends Model implements HasMedia
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function fileMedia(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'photo_person');
    }

    /**
     * @return BelongsToMany<Story, $this>
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_photo');
    }
}
