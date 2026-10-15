<?php

use App\Models\AudioRecording;
use App\Models\Person;
use App\Models\Photo;
use App\Models\Story;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Person/Story no longer implement HasMedia — that's the whole point of
 * this migration. To simulate pre-migration data (a Media row still owned
 * directly by a Person/Story under the old 'photo'/'gallery'/'audio'
 * collection names), create it through a model that still has the trait
 * (Photo), then rewrite its ownership columns back to what the old schema
 * looked like before running the command under test.
 */
function seedLegacyMedia(Person|Story $owner, string $collection, string $bytes, string $fileName): Media
{
    $carrier = Photo::create();
    $media = $carrier->addMediaFromString($bytes)->usingFileName($fileName)->preservingOriginal()->toMediaCollection('file');

    $media->model_type = $owner::class;
    $media->model_id = $owner->id;
    $media->collection_name = $collection;
    $media->save();

    $carrier->delete();

    return $media;
}

test('a person\'s featured photo becomes a standalone Photo tagged to them and stays their avatar', function () {
    $person = Person::factory()->create();
    seedLegacyMedia($person, 'photo', fakeImageBytes(), 'a.jpg');
    $featured = seedLegacyMedia($person, 'photo', fakeImageBytes(), 'b.jpg');
    $featured->setCustomProperty('featured', true);
    $featured->save();

    $this->artisan('media:migrate-to-photos')->assertExitCode(0);

    expect(Photo::count())->toBe(2);

    $person->refresh();
    $avatar = Photo::find($person->avatar_photo_id);

    expect($avatar)->not->toBeNull();
    expect($avatar->fileMedia()->file_name)->toBe('b.jpg');
    expect($avatar->people()->pluck('people.id')->all())->toBe([$person->id]);
});

test('a story\'s gallery photos become standalone photos tagged with the story\'s already-tagged people', function () {
    $story = Story::factory()->create();
    $alice = Person::factory()->create();
    $bob = Person::factory()->create();
    $story->people()->sync([$alice->id, $bob->id]);

    $media = seedLegacyMedia($story, 'gallery', fakeImageBytes(), 'g.jpg');
    $media->setCustomProperty('featured', true);
    $media->save();

    $this->artisan('media:migrate-to-photos')->assertExitCode(0);

    $story->refresh();
    $photo = Photo::find($story->featured_photo_id);

    expect($photo)->not->toBeNull();
    expect($photo->stories()->pluck('stories.id')->all())->toBe([$story->id]);
    expect($photo->people()->pluck('people.id')->all())->toEqualCanonicalizing([$alice->id, $bob->id]);
});

test('a story\'s audio becomes a standalone audio recording', function () {
    $story = Story::factory()->create();
    $wavHeader = 'RIFF'.pack('V', 36).'WAVE'.'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8).'data'.pack('V', 0);
    seedLegacyMedia($story, 'audio', $wavHeader, 'a.wav');

    $this->artisan('media:migrate-to-photos')->assertExitCode(0);

    $story->refresh();

    expect($story->audio_recording_id)->not->toBeNull();

    $recording = AudioRecording::find($story->audio_recording_id);
    expect($recording->fileMedia()->file_name)->toBe('a.wav');
});
