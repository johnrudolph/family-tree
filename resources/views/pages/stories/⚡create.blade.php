<?php

use App\Concerns\PicksExistingPhotos;
use App\Models\AudioRecording;
use App\Models\Person;
use App\Models\Photo;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryAdded;
use App\Notifications\SlackAnnouncement;
use App\Services\PageEditorService;
use App\Services\SlackNotifier;
use App\Support\RichTextSanitizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('New story')] class extends Component {
    use PicksExistingPhotos, WithFileUploads;

    public string $title = '';

    public string $body = '';

    public ?int $location_id = null;

    public string $start_date_precision = 'exact';

    public ?string $start_date = null;

    public ?string $start_year = null;

    public bool $has_end_date = false;

    public string $end_date_precision = 'exact';

    public ?string $end_date = null;

    public ?string $end_year = null;

    /** @var array<int, int> */
    public array $person_ids = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $audio = null;

    public ?int $existingAudioId = null;

    public function mount(): void
    {
        Gate::authorize('create', Story::class);
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    #[Computed]
    public function canUploadMedia(): bool
    {
        return Gate::allows('create', Photo::class);
    }

    #[Computed]
    public function availableAudioRecordings(): Collection
    {
        return AudioRecording::query()->latest()->limit(60)->get();
    }

    #[On('location-selected')]
    public function onLocationSelected(string $field, ?int $locationId): void
    {
        $this->location_id = $locationId;
    }

    public function save(): void
    {
        Gate::authorize('create', Story::class);

        $currentYear = (int) now()->format('Y');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:70'],
            'body' => ['nullable', 'string', 'max:50000'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'start_date_precision' => ['required', 'in:exact,year'],
            'start_date' => ['required_if:start_date_precision,exact', 'nullable', 'date'],
            'start_year' => ['required_if:start_date_precision,year', 'nullable', 'integer', 'min:1000', 'max:'.$currentYear],
            'has_end_date' => ['boolean'],
            'end_date_precision' => ['required_if:has_end_date,true', 'in:exact,year'],
            'end_date' => ['nullable', 'date'],
            'end_year' => ['nullable', 'integer', 'min:1000', 'max:'.($currentYear + 1)],
            'person_ids' => ['array'],
            'person_ids.*' => ['exists:people,id'],
            'selectedPhotoIds' => ['array'],
            'selectedPhotoIds.*' => ['exists:photos,id'],
            'photos.*' => ['image', 'max:20480'],
            'audio' => ['nullable', 'file', 'mimes:mp3,mp4,m4a,wav,ogg,webm,aac', 'max:102400'],
            'existingAudioId' => ['nullable', 'integer', 'exists:audio_recordings,id'],
        ]);

        $story = Story::create([
            'title' => $validated['title'],
            'body' => RichTextSanitizer::clean($validated['body']),
            'location_id' => $validated['location_id'],
            'start_date' => $validated['start_date_precision'] === 'year' ? "{$validated['start_year']}-01-01" : $validated['start_date'],
            'start_date_precision' => $validated['start_date_precision'],
            'end_date' => $validated['has_end_date'] ? ($validated['end_date_precision'] === 'year' ? "{$validated['end_year']}-01-01" : $validated['end_date']) : null,
            'end_date_precision' => $validated['has_end_date'] ? $validated['end_date_precision'] : null,
            'created_by' => Auth::id(),
        ]);

        $story->people()->sync($validated['person_ids']);

        if ($validated['selectedPhotoIds']) {
            $story->photos()->attach($validated['selectedPhotoIds']);
        }

        if ($this->photos) {
            Gate::authorize('create', Photo::class);

            foreach ($this->photos as $uploaded) {
                $photo = Photo::create(['uploaded_by' => Auth::id()]);
                $photo->addMedia($uploaded->getRealPath())
                    ->usingFileName($uploaded->getClientOriginalName())
                    ->toMediaCollection('file');
                $story->photos()->attach($photo->id);
            }
        }

        if ($validated['existingAudioId']) {
            $story->audio_recording_id = $validated['existingAudioId'];
        } elseif ($this->audio) {
            Gate::authorize('create', AudioRecording::class);

            $recording = AudioRecording::create(['uploaded_by' => Auth::id()]);
            $recording->addMedia($this->audio->getRealPath())
                ->usingFileName($this->audio->getClientOriginalName())
                ->toMediaCollection('file');
            $story->audio_recording_id = $recording->id;
        }

        if ($story->isDirty('audio_recording_id')) {
            $story->save();
        }

        app(PageEditorService::class)->grantOwner($story, Auth::user());

        Notification::send(
            User::query()->where('notify_on_new_stories', true)->where('id', '!=', Auth::id())->get(),
            new StoryAdded($story),
        );

        app(SlackNotifier::class)->send(new SlackAnnouncement(
            "{$story->creator->name} added a story: *{$story->title}*\n{$story->ogShareUrl()}"
        ));

        $this->redirect(route('stories.show', $story), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('New story') }}</flux:heading>

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <flux:input wire:model="title" :label="__('Title')" :description="__('Shown as a headline on the timeline — keep it short.')" maxlength="70" required />
        <div wire:ignore x-data x-init="initStoryTagging($el, @js($this->people()->map(fn ($p) => ['id' => $p->id, 'name' => $p->fullName()])))">
            <flux:editor wire:model="body" :label="__('Story')" :description="__('Type [[ to tag a person by name — they\'ll get a link, and this story will show up on their page.')" toolbar="heading | bold italic underline strike | bullet ordered blockquote | link" class="**:data-[slot=content]:min-h-64" />
        </div>

        <livewire:location-picker field="story" :location-id="$location_id" :label="__('Where did this happen?')" wire:key="story-location-picker" />

        <flux:separator />

        <flux:radio.group wire:model.live="start_date_precision" :label="__('When did this happen?')">
            <flux:radio value="exact" label="{{ __('Exact date') }}" />
            <flux:radio value="year" label="{{ __('Year only') }}" />
        </flux:radio.group>
        <div x-show="$wire.start_date_precision === 'exact'">
            <flux:input wire:model="start_date" type="date" :label="__('Start date')" />
        </div>
        <div x-show="$wire.start_date_precision === 'year'">
            <flux:input wire:model="start_year" type="number" :label="__('Year')" placeholder="1954" />
        </div>

        <flux:checkbox wire:model.live="has_end_date" :label="__('This spans a range of time (has an end date)')" />
        <div x-show="$wire.has_end_date" class="flex flex-col gap-6">
            <flux:radio.group wire:model.live="end_date_precision" :label="__('End date precision')">
                <flux:radio value="exact" label="{{ __('Exact date') }}" />
                <flux:radio value="year" label="{{ __('Year only') }}" />
            </flux:radio.group>
            <div x-show="$wire.end_date_precision === 'exact'">
                <flux:input wire:model="end_date" type="date" :label="__('End date')" />
            </div>
            <div x-show="$wire.end_date_precision === 'year'">
                <flux:input wire:model="end_year" type="number" :label="__('Year')" placeholder="1962" />
            </div>
        </div>

        <flux:separator />

        <flux:select variant="listbox" searchable multiple wire:model="person_ids" :label="__('Who is this story about?')" :placeholder="__('Search people…')">
            @foreach ($this->people() as $person)
                <flux:select.option value="{{ $person->id }}">{{ $person->fullName() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:separator />

        <div>
            <flux:text class="font-medium">{{ __('Photos') }}</flux:text>
            <flux:text class="text-sm text-zinc-500">{{ __('Pick from the photo library — click any to select.') }}</flux:text>
            <div class="mt-2">
                @include('partials.photo-picker')
            </div>
        </div>

        @if ($this->canUploadMedia)
            @include('partials.photo-upload-dropzone', ['model' => 'photos', 'label' => __('Or upload new photos — you can pick a featured image for the timeline after publishing, from the edit page.')])
            @if ($photos)
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($photos as $photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="aspect-square rounded object-cover" alt="">
                    @endforeach
                </div>
            @endif
        @endif

        <flux:separator />

        <div>
            <flux:text class="font-medium">{{ __('Audio (optional)') }}</flux:text>
            <flux:text class="text-sm text-zinc-500">{{ __('An oral history recording — someone speaking about this story. Shown with a small player.') }}</flux:text>

            @if ($this->availableAudioRecordings->isNotEmpty())
                <flux:select wire:model="existingAudioId" variant="listbox" searchable clearable class="mt-2" :placeholder="__('Use an existing recording…')">
                    @foreach ($this->availableAudioRecordings as $recording)
                        <flux:select.option value="{{ $recording->id }}">{{ $recording->description ?: "Recording #{$recording->id}" }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($this->canUploadMedia && ! $existingAudioId)
                <div x-data x-on:livewire-upload-error="$flux.toast(@js(__('Audio upload failed — the file may be too large or an unsupported format.')), { variant: 'danger', duration: 8000 })" class="mt-2">
                    <flux:input type="file" wire:model="audio" accept="audio/*" :label="__('Or upload a new recording')" />
                </div>
            @endif
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Publish') }}</flux:button>
            <flux:button :href="route('stories.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
