<?php

namespace App\Models;

use Database\Factories\AudioRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A standalone audio recording — same shape as Photo, but reuse across
 * stories is the rare case rather than the common one, so stories
 * reference it through a plain `audio_recording_id` column instead of a
 * pivot table.
 *
 * @property int $id
 * @property int|null $uploaded_by
 * @property string|null $description
 * @property Carbon|null $date
 * @property string|null $date_precision
 * @property-read User|null $uploader
 * @property-read Collection<int, Story> $stories
 */
#[Fillable(['uploaded_by', 'description', 'date', 'date_precision'])]
class AudioRecording extends Model implements HasMedia
{
    /** @use HasFactory<AudioRecordingFactory> */
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
        $this->addMediaCollection('file')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm', 'audio/aac']);
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
     * @return HasMany<Story, $this>
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }
}
