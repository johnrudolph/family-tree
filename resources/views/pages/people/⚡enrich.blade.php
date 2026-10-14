<?php

use App\Models\Person;
use App\Models\Photo;
use App\Support\MediaUrl;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Photo & details')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Person $person;

    public string $address = '';

    public string $phone = '';

    public string $contact_email = '';

    /** @var array<int, string> */
    public array $social_links = [];

    public string $newSocialLink = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    public function mount(Person $person): void
    {
        Gate::authorize('manageEnrichment', $person);

        $this->person = $person;
        $this->address = $person->address ?? '';
        $this->phone = $person->phone ?? '';
        $this->contact_email = $person->contact_email ?? '';
        $this->social_links = $person->social_links ?? [];
    }

    #[Computed]
    public function isSelf(): bool
    {
        return Auth::user()->person_id === $this->person->id;
    }

    public function addSocialLink(): void
    {
        $validated = $this->validate(['newSocialLink' => ['required', 'url', 'max:255']])['newSocialLink'];

        $this->social_links[] = $validated;
        $this->newSocialLink = '';
    }

    public function removeSocialLink(int $index): void
    {
        unset($this->social_links[$index]);
        $this->social_links = array_values($this->social_links);
    }

    public function featurePhoto(int $photoId): void
    {
        Gate::authorize('manageEnrichment', $this->person);

        $photo = $this->person->photos->firstWhere('id', $photoId);
        abort_unless($photo, 404);

        $this->person->avatar_photo_id = $photo->id;
        $this->person->save();

        Flux::toast(variant: 'success', text: __('Featured photo updated.'));
    }

    public function save(): void
    {
        Gate::authorize('manageEnrichment', $this->person);

        $validated = $this->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'newPhotos.*' => ['image', 'max:20480'],
        ]);

        $this->person->update([
            'address' => $validated['address'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'contact_email' => $validated['contact_email'] ?: null,
            'social_links' => $this->social_links ?: null,
            'consented_at' => $this->isSelf ? ($this->person->consented_at ?? now()) : $this->person->consented_at,
        ]);

        if ($this->newPhotos) {
            // Uploading your own profile photo is a narrower, pre-existing
            // allowance (manageEnrichment, checked above) — it doesn't go
            // through the admin-only Photo library upload gate.
            foreach ($this->newPhotos as $uploaded) {
                $photo = Photo::create(['uploaded_by' => Auth::id()]);
                $photo->addMedia($uploaded->getRealPath())
                    ->usingFileName($uploaded->getClientOriginalName())
                    ->toMediaCollection('file');
                $photo->people()->attach($this->person->id);

                if ($this->person->avatar_photo_id === null) {
                    $this->person->avatar_photo_id = $photo->id;
                }
            }

            if ($this->person->isDirty('avatar_photo_id')) {
                $this->person->save();
            }

            $this->person->unsetRelation('photos');
        }

        Flux::toast(
            variant: 'success',
            text: $this->isSelf
                ? __('Saved. This information is only ever editable by you.')
                : __('Saved.'),
        );

        $this->redirect(route('people.show', $this->person), navigate: true);
    }
}; ?>

<section class="w-full max-w-lg">
    @if ($this->isSelf)
        <flux:heading level="1">{{ __('My details') }}</flux:heading>
        <flux:subheading>
            {{ __('This information is optional, self-managed, and only ever writable by you — no one else can add or suggest it for you.') }}
        </flux:subheading>
    @else
        <flux:heading level="1">{{ __('Memorial photo & details for') }} {{ $person->fullName() }}</flux:heading>
        <flux:subheading>
            {{ __('Since this person has passed, any editor of their page can add a photo and memorial details on their behalf.') }}
        </flux:subheading>
    @endif

    <form wire:submit="save" class="mt-6 flex flex-col gap-6">
        <div class="flex items-center gap-4">
            <x-person-avatar :person="$person" size="xl" />
            <div class="flex-1" x-data x-on:livewire-upload-error="$flux.toast(@js(__('Photo upload failed — try smaller images, or check your connection and try again.')), { variant: 'danger', duration: 8000 })">
                <flux:input type="file" wire:model="newPhotos" multiple accept="image/*" :label="__('Add photos')" />
                @if ($newPhotos)
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('New photos selected — save to apply.') }}</flux:text>
                @endif
            </div>
        </div>

        @if ($person->photos->isNotEmpty())
            <div>
                <flux:text class="text-sm font-medium">{{ __('Photos — click one to feature it everywhere else on the site') }}</flux:text>
                <div class="mt-2 grid grid-cols-4 gap-2">
                    @foreach ($person->photos as $photo)
                        <button
                            type="button"
                            wire:click="featurePhoto({{ $photo->id }})"
                            wire:key="photo-{{ $photo->id }}"
                            class="relative aspect-square overflow-hidden rounded {{ $photo->id === $person->avatar_photo_id ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-zinc-800' : '' }}"
                        >
                            <img src="{{ MediaUrl::of($photo->fileMedia()) }}" class="h-full w-full object-cover" alt="">
                            @if ($photo->id === $person->avatar_photo_id)
                                <flux:badge size="sm" class="absolute bottom-1 left-1">{{ __('Featured') }}</flux:badge>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <flux:input wire:model="contact_email" type="email" :label="__('Contact email')" />
        <flux:input wire:model="phone" :label="__('Phone')" />
        <flux:input wire:model="address" :label="__('Address')" />

        <div>
            <flux:text class="font-medium">{{ __('Social links') }}</flux:text>
            <div class="mt-2 space-y-2">
                @foreach ($social_links as $index => $link)
                    <div class="flex items-center gap-2">
                        <flux:text class="flex-1 truncate text-sm">{{ $link }}</flux:text>
                        <flux:button wire:click="removeSocialLink({{ $index }})" size="sm" variant="ghost" icon="x-mark" />
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex gap-2">
                <flux:input wire:model="newSocialLink" placeholder="https://…" class="flex-1" />
                <flux:button wire:click="addSocialLink" type="button" size="sm">{{ __('Add') }}</flux:button>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button :href="route('people.show', $person)" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
