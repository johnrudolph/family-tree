<?php

namespace App\Models;

use App\Concerns\HasWikiWorkflow;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property Carbon $start_date
 * @property string $start_date_precision
 * @property Carbon|null $end_date
 * @property string|null $end_date_precision
 * @property int|null $location_id
 * @property int $created_by
 * @property-read User $creator
 * @property-read Collection<int, Person> $people
 * @property-read Location|null $location
 */
#[Fillable(['title', 'slug', 'body', 'location_id', 'start_date', 'start_date_precision', 'end_date', 'end_date_precision', 'created_by'])]
class Story extends Model implements HasMedia
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory, HasWikiWorkflow, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('audio')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm', 'audio/aac']);
    }

    public function audioMedia(): ?Media
    {
        return $this->getFirstMedia('audio');
    }

    public function hasAudio(): bool
    {
        return $this->audioMedia() !== null;
    }

    /**
     * @return Collection<int, Media>
     */
    public function galleryMedia(): Collection
    {
        return $this->getMedia('gallery');
    }

    /**
     * The single gallery image chosen to represent this story on the
     * timeline and in link previews — the first upload by default,
     * until an editor explicitly picks one via `featureImage()`.
     */
    public function featuredImage(): ?Media
    {
        $gallery = $this->galleryMedia();

        return $gallery->first(fn (Media $media) => $media->getCustomProperty('featured') === true)
            ?? $gallery->first();
    }

    public function featureImage(Media $media): void
    {
        foreach ($this->galleryMedia() as $item) {
            if ($item->getCustomProperty('featured') && $item->id !== $media->id) {
                $item->setCustomProperty('featured', false);
                $item->save();
            }
        }

        $media->setCustomProperty('featured', true);
        $media->save();
    }

    protected static function booted(): void
    {
        static::creating(function (Story $story) {
            if (empty($story->slug)) {
                $story->slug = static::uniqueSlugFor($story->title);
            }
        });
    }

    public static function uniqueSlugFor(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'story_person');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function wikiTitle(): string
    {
        return $this->title;
    }

    public function wikiShowUrl(): string
    {
        return route('stories.show', $this);
    }

    public function wikiSuggestionsUrl(): string
    {
        return route('stories.suggestions', $this);
    }
}
