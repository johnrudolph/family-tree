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
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property int $created_by
 * @property-read User $creator
 * @property-read Collection<int, Person> $people
 */
#[Fillable(['title', 'slug', 'body', 'created_by'])]
class Story extends Model implements HasMedia
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory, HasWikiWorkflow, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery');
    }

    /**
     * @return Collection<int, Media>
     */
    public function galleryMedia(): Collection
    {
        return $this->getMedia('gallery');
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
