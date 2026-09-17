<?php

use App\Models\Person;
use App\Models\Story;
use App\Services\PageEditorService;
use App\Support\RichTextSanitizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('New story')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public string $body = '';

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

    public function mount(): void
    {
        Gate::authorize('create', Story::class);
    }

    public function people()
    {
        return Person::query()->orderBy('first_name')->get();
    }

    public function save(): void
    {
        Gate::authorize('create', Story::class);

        $currentYear = (int) now()->format('Y');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:70'],
            'body' => ['nullable', 'string', 'max:50000'],
            'start_date_precision' => ['required', 'in:exact,year'],
            'start_date' => ['required_if:start_date_precision,exact', 'nullable', 'date'],
            'start_year' => ['required_if:start_date_precision,year', 'nullable', 'integer', 'min:1000', 'max:'.$currentYear],
            'has_end_date' => ['boolean'],
            'end_date_precision' => ['required_if:has_end_date,true', 'in:exact,year'],
            'end_date' => ['nullable', 'date'],
            'end_year' => ['nullable', 'integer', 'min:1000', 'max:'.($currentYear + 1)],
            'person_ids' => ['array'],
            'person_ids.*' => ['exists:people,id'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $story = Story::create([
            'title' => $validated['title'],
            'body' => RichTextSanitizer::clean($validated['body']),
            'start_date' => $validated['start_date_precision'] === 'year' ? "{$validated['start_year']}-01-01" : $validated['start_date'],
            'start_date_precision' => $validated['start_date_precision'],
            'end_date' => $validated['has_end_date'] ? ($validated['end_date_precision'] === 'year' ? "{$validated['end_year']}-01-01" : $validated['end_date']) : null,
            'end_date_precision' => $validated['has_end_date'] ? $validated['end_date_precision'] : null,
            'created_by' => Auth::id(),
        ]);

        $story->people()->sync($validated['person_ids']);

        foreach ($this->photos as $photo) {
            $story->addMedia($photo->getRealPath())
                ->usingFileName($photo->getClientOriginalName())
                ->toMediaCollection('gallery');
        }

        app(PageEditorService::class)->grantOwner($story, Auth::user());

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

        <flux:input type="file" wire:model="photos" multiple accept="image/*" :label="__('Photos')" :description="__('You can pick a featured image for the timeline after publishing, from the edit page.')" />
        @if ($photos)
            <div class="grid grid-cols-4 gap-2">
                @foreach ($photos as $photo)
                    <img src="{{ $photo->temporaryUrl() }}" class="aspect-square rounded object-cover" alt="">
                @endforeach
            </div>
        @endif

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Publish') }}</flux:button>
            <flux:button :href="route('stories.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
