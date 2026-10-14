<?php

namespace App\Console\Commands;

use App\Models\AudioRecording;
use App\Models\Person;
use App\Models\Photo;
use App\Models\Story;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:migrate-to-photos')]
#[Description('One-time backfill: turns every Person photo and Story gallery/audio Media row into a standalone Photo/AudioRecording, preserving the physical file (no re-upload, no file move — just re-pointing ownership). Queries the media table directly rather than through Person/Story, since those models no longer implement HasMedia by the time this runs.')]
class MigrateMediaToPhotos extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DB::transaction(function () {
            $this->migratePersonPhotos();
            $this->migrateStoryGalleries();
            $this->migrateStoryAudio();
        });

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function migratePersonPhotos(): void
    {
        $mediaByPerson = Media::query()
            ->where('model_type', Person::class)
            ->where('collection_name', 'photo')
            ->orderBy('order_column')
            ->get()
            ->groupBy('model_id');

        foreach ($mediaByPerson as $personId => $mediaItems) {
            $person = Person::find($personId);

            if (! $person) {
                continue;
            }

            foreach ($mediaItems as $media) {
                $photo = $this->convertMedia($media);
                $photo->people()->attach($person->id);

                if ($media->getCustomProperty('featured') === true || $person->avatar_photo_id === null) {
                    $person->avatar_photo_id = $photo->id;
                }
            }

            if ($person->isDirty('avatar_photo_id')) {
                $person->save();
            }
        }
    }

    private function migrateStoryGalleries(): void
    {
        $mediaByStory = Media::query()
            ->where('model_type', Story::class)
            ->where('collection_name', 'gallery')
            ->orderBy('order_column')
            ->get()
            ->groupBy('model_id');

        foreach ($mediaByStory as $storyId => $mediaItems) {
            $story = Story::find($storyId);

            if (! $story) {
                continue;
            }

            $taggedPeopleIds = $story->people()->pluck('people.id');

            foreach ($mediaItems as $media) {
                $photo = $this->convertMedia($media);

                $photo->stories()->attach($story->id);

                if ($taggedPeopleIds->isNotEmpty()) {
                    $photo->people()->attach($taggedPeopleIds);
                }

                if ($media->getCustomProperty('featured') === true || $story->featured_photo_id === null) {
                    $story->featured_photo_id = $photo->id;
                }
            }

            if ($story->isDirty('featured_photo_id')) {
                $story->save();
            }
        }
    }

    private function migrateStoryAudio(): void
    {
        $mediaItems = Media::query()
            ->where('model_type', Story::class)
            ->where('collection_name', 'audio')
            ->get();

        foreach ($mediaItems as $media) {
            $story = Story::find($media->model_id);

            if (! $story) {
                continue;
            }

            $recording = AudioRecording::create(['uploaded_by' => $story->created_by]);

            $media->model_type = AudioRecording::class;
            $media->model_id = $recording->id;
            $media->collection_name = 'file';
            $media->save();

            $story->audio_recording_id = $recording->id;
            $story->save();
        }
    }

    private function convertMedia(Media $media): Photo
    {
        $photo = Photo::create(['uploaded_by' => null]);

        $media->model_type = Photo::class;
        $media->model_id = $photo->id;
        $media->collection_name = 'file';
        $media->save();

        return $photo;
    }
}
