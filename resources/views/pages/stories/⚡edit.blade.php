<?php

use App\Models\Person;
use App\Models\Story;
use App\Services\RevisionService;
use App\Services\StoryDeletionService;
use App\Support\MediaUrl;
use App\Support\RichTextSanitizer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit story')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Story $story;

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
    public array $newPhotos = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $audio = null;

    public string $deleteConfirmationName = '';

    public function mount(Story $story): void
    {
        Gate::authorize('update', $story);

        $this->story = $story;
        $this->title = $story->title;
        $this->body = $story->body ?? '';
        $this->location_id = $story->location_id;
        $this->start_date_precision = $story->start_date_precision;
        $this->start_date = $story->start_date_precision === 'exact' ? $story->start_date->toDateString() : null;
        $this->start_year = $story->start_date_precision === 'year' ? $story->start_date->format('Y') : null;
        $this->has_end_date = $story->end_date !== null;
        $this->end_date_precision = $story->end_date_precision ?? 'exact';
        $this->end_date = $story->end_date_precision === 'exact' ? $story->end_date?->toDateString() : null;
        $this->end_year = $story->end_date_precision === 'year' ? $story->end_date?->format('Y') : null;
        $this->person_ids = $story->people->pluck('id')->all();
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    #[On('location-selected')]
    public function onLocationSelected(string $field, ?int $locationId): void
    {
        $this->location_id = $locationId;
    }

    public function featureImage(int $mediaId): void
    {
        Gate::authorize('update', $this->story);

        $media = $this->story->galleryMedia()->firstWhere('id', $mediaId);
        abort_unless($media, 404);

        $this->story->featureImage($media);

        Flux::toast(variant: 'success', text: __('Featured image updated.'));
    }

    public function removeAudio(): void
    {
        Gate::authorize('update', $this->story);

        $this->story->clearMediaCollection('audio');

        Flux::toast(variant: 'success', text: __('Audio removed.'));
    }

    #[Computed]
    public function canDelete(): bool
    {
        return Gate::allows('delete', $this->story);
    }

    public function deleteStory(): void
    {
        Gate::authorize('delete', $this->story);

        $this->validate([
            'deleteConfirmationName' => ['required', function ($attribute, $value, $fail) {
                if ($value !== $this->story->title) {
                    $fail(__('That doesn\'t match — type the title exactly to confirm.'));
                }
            }],
        ]);

        app(StoryDeletionService::class)->delete($this->story);

        Flux::toast(variant: 'success', text: __('Story deleted.'));

        $this->redirect(route('stories.index'), navigate: true);
    }

    public function save(): void
    {
        Gate::authorize('update', $this->story);

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
            'newPhotos.*' => ['image', 'max:20480'],
            'audio' => ['nullable', 'file', 'mimes:mp3,mp4,m4a,wav,ogg,webm,aac', 'max:102400'],
        ]);

        $data = [
            'title' => $validated['title'],
            'body' => RichTextSanitizer::clean($validated['body']),
            'location_id' => $validated['location_id'],
            'start_date' => $validated['start_date_precision'] === 'year' ? "{$validated['start_year']}-01-01" : $validated['start_date'],
            'start_date_precision' => $validated['start_date_precision'],
            'end_date' => $validated['has_end_date'] ? ($validated['end_date_precision'] === 'year' ? "{$validated['end_year']}-01-01" : $validated['end_date']) : null,
            'end_date_precision' => $validated['has_end_date'] ? $validated['end_date_precision'] : null,
        ];

        $this->story->update($data);
        $this->story->people()->sync($validated['person_ids']);

        foreach ($this->newPhotos as $photo) {
            $this->story->addMedia($photo->getRealPath())
                ->usingFileName($photo->getClientOriginalName())
                ->toMediaCollection('gallery');
        }

        if ($this->audio) {
            $this->story->addMedia($this->audio->getRealPath())
                ->usingFileName($this->audio->getClientOriginalName())
                ->toMediaCollection('audio');
        }

        app(RevisionService::class)->record($this->story, Auth::user(), $data);

        Flux::toast(variant: 'success', text: __('Saved.'));

        $this->redirect(route('stories.show', $this->story), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    <flux:heading level="1">{{ __('Edit story') }}</flux:heading>

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

        @if ($story->galleryMedia()->isNotEmpty())
            <div>
                <flux:text class="text-sm font-medium">{{ __('Gallery — click a photo to feature it on the timeline') }}</flux:text>
                <div class="mt-2 grid grid-cols-4 gap-2">
                    @foreach ($story->galleryMedia() as $media)
                        <button
                            type="button"
                            wire:click="featureImage({{ $media->id }})"
                            wire:key="gallery-{{ $media->id }}"
                            class="relative aspect-square overflow-hidden rounded {{ $media->getCustomProperty('featured') ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-zinc-800' : '' }}"
                        >
                            <img src="{{ MediaUrl::of($media) }}" class="h-full w-full object-cover" alt="">
                            @if ($media->getCustomProperty('featured'))
                                <flux:badge size="sm" class="absolute bottom-1 left-1">{{ __('Featured') }}</flux:badge>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div x-data x-on:livewire-upload-error="$flux.toast(@js(__('Photo upload failed — the photos you selected likely add up to more than the server allows in one upload. Try again with fewer photos at once, or smaller ones.')), { variant: 'danger', duration: 8000 })">
            <flux:input type="file" wire:model="newPhotos" multiple accept="image/*" :label="__('Add photos')" />
        </div>

        <flux:separator />

        @if ($story->hasAudio())
            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <flux:icon.volume-2 class="size-5 text-zinc-400" />
                    <flux:text class="text-sm">{{ $story->audioMedia()->file_name }}</flux:text>
                </div>
                <flux:button type="button" wire:click="removeAudio" wire:confirm="{{ __('Remove this audio?') }}" size="sm" variant="danger">{{ __('Remove') }}</flux:button>
            </div>
        @else
            <div x-data x-on:livewire-upload-error="$flux.toast(@js(__('Audio upload failed — the file may be too large or an unsupported format.')), { variant: 'danger', duration: 8000 })">
                <flux:input type="file" wire:model="audio" accept="audio/*" :label="__('Audio (optional)')" :description="__('An oral history recording — someone speaking about this story. Shown with a small player.')" />
            </div>
        @endif

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button :href="route('stories.show', $story)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>

    @if ($this->canDelete)
        <flux:separator class="mt-10" />

        <div class="mt-10 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="2" size="sm">{{ __('Danger zone') }}</flux:heading>

            <flux:modal.trigger name="delete-story">
                <flux:button variant="danger" size="sm" class="mt-3">{{ __('Delete story') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="delete-story" class="w-96" @close="$set('deleteConfirmationName', '')">
            <form wire:submit="deleteStory" class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Delete :title?', ['title' => $story->title]) }}</flux:heading>
                    <flux:subheading class="mt-2">
                        {{ __('This can\'t be undone. Its photos and audio, tags to people, edit history, and pending suggestions are all deleted too.') }}
                    </flux:subheading>
                </div>

                <flux:input
                    wire:model="deleteConfirmationName"
                    :label="__('Type :title to confirm', ['title' => $story->title])"
                />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="danger"
                        type="submit"
                        x-bind:disabled="$wire.deleteConfirmationName !== @js($story->title)"
                    >
                        {{ __('Delete story') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
