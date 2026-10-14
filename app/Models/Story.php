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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
 * @property int|null $featured_photo_id
 * @property int|null $audio_recording_id
 * @property int $created_by
 * @property-read User $creator
 * @property-read Collection<int, Person> $people
 * @property-read Location|null $location
 * @property-read Collection<int, Photo> $photos
 * @property-read Photo|null $featuredPhoto
 * @property-read AudioRecording|null $audioRecording
 */
#[Fillable(['title', 'slug', 'body', 'location_id', 'start_date', 'start_date_precision', 'end_date', 'end_date_precision', 'created_by', 'featured_photo_id', 'audio_recording_id'])]
class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory, HasWikiWorkflow;

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

    /**
     * @return BelongsToMany<Photo, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'story_photo')->orderBy('photos.created_at');
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function featuredPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'featured_photo_id');
    }

    /**
     * The photo chosen to represent this story on the timeline and in link
     * previews — explicitly picked via `featured_photo_id`, or the
     * earliest-linked photo as a fallback until an editor picks one.
     */
    public function featuredImage(): ?Photo
    {
        return $this->featuredPhoto ?? $this->photos->first();
    }

    /**
     * @return BelongsTo<AudioRecording, $this>
     */
    public function audioRecording(): BelongsTo
    {
        return $this->belongsTo(AudioRecording::class);
    }

    public function hasAudio(): bool
    {
        return $this->audio_recording_id !== null;
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

    /**
     * A signed, unauthenticated URL that bots can fetch to unfurl a link
     * preview for this story — the real page stays behind login.
     */
    public function ogShareUrl(): string
    {
        return URL::signedRoute('og.story.show', ['story' => $this]);
    }

    public function ogImageUrl(): string
    {
        return URL::signedRoute('og.story.image', ['story' => $this]);
    }

    public function wikiSuggestionsUrl(): string
    {
        return route('stories.suggestions', $this);
    }
}
