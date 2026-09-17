<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PageEditorService;
use App\Services\RelationshipLabelService;
use App\Services\RelationshipService;
use App\Services\RevisionService;
use App\Support\FamilyTreeSerializer;
use App\Support\StoryBodyParser;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Person $person;

    public bool $showEditors = false;

    public bool $showHistory = false;

    public bool $showManageRelationships = false;

    public string $relType = 'parent';

    public string $relMode = 'existing';

    public ?int $relExistingPersonId = null;

    public string $relNewFirstName = '';

    public string $relNewMiddleName = '';

    public string $relNewLastName = '';

    public ?string $relNewDob = null;

    public bool $relNewIsLiving = true;

    public ?string $relNewDod = null;

    public string $relSpouseStatus = 'married';

    /** @var array<int, int> child ids to also link to the new spouse as parent */
    public array $relAlsoParentOfChildIds = [];

    /** @var array<int, int> spouse ids to also link to the new child as parent */
    public array $relAlsoCoParentIds = [];

    /** @var array<int, int> parent ids to also link to the new sibling as parent */
    public array $relAlsoSiblingParentIds = [];

    public ?int $newEditorUserId = null;

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    #[Computed]
    public function isSelf(): bool
    {
        return Auth::user()->person_id === $this->person->id;
    }

    #[Computed]
    public function relationshipToViewer(): ?string
    {
        $viewer = Auth::user()->person;

        if (! $viewer || $this->isSelf) {
            return null;
        }

        return app(RelationshipLabelService::class)->label($viewer, $this->person);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Gate::allows('update', $this->person);
    }

    #[Computed]
    public function canManageEnrichment(): bool
    {
        return Gate::allows('manageEnrichment', $this->person);
    }

    /**
     * A simplified, read-only visual of this person's immediate family —
     * shown on their page in place of a plain relationship list.
     */
    #[Computed]
    public function familyTreeData(): array
    {
        return FamilyTreeSerializer::toChartData();
    }

    #[Computed]
    public function pendingSuggestionCount(): int
    {
        return $this->canEdit ? $this->person->pendingSuggestions()->count() : 0;
    }

    /**
     * Every story this person appears in — the curated "who is this about"
     * list plus anything they're `[[tagged]]` in within a story body, merged
     * and deduped since a reader just wants "stories I show up in."
     */
    #[Computed]
    public function relatedStories()
    {
        return $this->person->stories
            ->merge(StoryBodyParser::storiesTagging($this->person))
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();
    }

    #[Computed]
    public function candidatePeople()
    {
        return app(RelationshipService::class)->candidatesFor($this->person);
    }

    #[Computed]
    public function candidateStepchildren()
    {
        return app(RelationshipService::class)->candidateStepchildren($this->person);
    }

    #[Computed]
    public function candidateCoParents()
    {
        return app(RelationshipService::class)->candidateCoParents($this->person);
    }

    #[Computed]
    public function existingParents()
    {
        return app(RelationshipService::class)->existingParents($this->person);
    }

    /**
     * Siblings not already shown as a removable row above — only the legacy
     * case of sharing a parent without an explicit sibling relationship yet.
     */
    #[Computed]
    public function derivedOnlySiblings()
    {
        $explicitIds = $this->relationshipRows->pluck('other.id');

        return $this->person->siblings()->reject(fn ($sibling) => $explicitIds->contains($sibling->id));
    }

    #[Computed]
    public function relationshipRows()
    {
        return Relationship::query()
            ->where('person_a_id', $this->person->id)
            ->orWhere('person_b_id', $this->person->id)
            ->with(['personA', 'personB'])
            ->get()
            ->map(function (Relationship $relationship) {
                $isA = $relationship->person_a_id === $this->person->id;
                $other = $isA ? $relationship->personB : $relationship->personA;

                $label = match (true) {
                    $relationship->type === 'parent_child' && $isA => __('Child'),
                    $relationship->type === 'parent_child' && ! $isA => __('Parent'),
                    $relationship->type === 'sibling' => __('Sibling'),
                    default => __('Spouse'),
                };

                return [
                    'id' => $relationship->id,
                    'label' => $label,
                    'other' => $other,
                ];
            });
    }

    #[Computed]
    public function editors()
    {
        return $this->person->pageEditors()->with('user')->orderBy('role')->get();
    }

    #[Computed]
    public function candidateUsers()
    {
        $existingIds = $this->person->pageEditors()->pluck('user_id');

        return User::query()->whereNotIn('id', $existingIds)->orderBy('name')->get();
    }

    /**
     * Reset the "also link" suggestions whenever the relationship type changes,
     * defaulting to everyone selected — the user un-checks the ones that don't apply.
     */
    public function updatedRelType(): void
    {
        $this->seedAlsoLinkSuggestions();
    }

    /**
     * Populate the "also link" pill defaults for the current relType. Called
     * both when the user switches relType and after a successful submit —
     * the latter matters because the arrays are otherwise left stale (empty,
     * from the post-submit reset) if someone adds a second relationship of
     * the same type without touching the relType radio again, e.g. adding
     * two siblings back to back from an already-open panel.
     */
    private function seedAlsoLinkSuggestions(): void
    {
        $this->relAlsoParentOfChildIds = $this->relType === 'spouse'
            ? $this->candidateStepchildren->pluck('id')->all()
            : [];

        $this->relAlsoCoParentIds = $this->relType === 'child'
            ? $this->candidateCoParents->pluck('id')->all()
            : [];

        $this->relAlsoSiblingParentIds = $this->relType === 'sibling'
            ? $this->existingParents->pluck('id')->all()
            : [];
    }

    /**
     * Remove a suggested "also link" pill before creating the relationship.
     * $property is restricted to the known suggestion arrays since it's
     * client-controlled via the pill component's wire:click call.
     */
    public function removeSuggestion(string $property, int $id): void
    {
        abort_unless(in_array($property, ['relAlsoParentOfChildIds', 'relAlsoCoParentIds', 'relAlsoSiblingParentIds'], true), 403);

        $this->{$property} = array_values(array_diff($this->{$property}, [$id]));
    }

    public function addRelationship(): void
    {
        Gate::authorize('update', $this->person);

        $validated = $this->validate([
            'relType' => ['required', 'in:parent,child,spouse,sibling'],
            'relMode' => ['required', 'in:existing,new'],
            'relExistingPersonId' => ['required_if:relMode,existing', 'nullable', 'exists:people,id'],
            'relNewFirstName' => ['required_if:relMode,new', 'nullable', 'string', 'max:255'],
            'relNewMiddleName' => ['nullable', 'string', 'max:255'],
            'relNewLastName' => ['nullable', 'string', 'max:255'],
            'relNewDob' => ['nullable', 'date'],
            'relNewIsLiving' => ['boolean'],
            'relNewDod' => ['nullable', 'date'],
            'relSpouseStatus' => ['required_if:relType,spouse', 'in:married,divorced,separated'],
        ]);

        $relationships = app(RelationshipService::class);

        try {
            DB::transaction(function () use ($validated, $relationships) {
                if ($this->relMode === 'new') {
                    $other = Person::create([
                        'first_name' => $validated['relNewFirstName'],
                        'middle_name' => $validated['relNewMiddleName'] ?: null,
                        'last_name' => $validated['relNewLastName'] ?: null,
                        'dob' => $validated['relNewDob'] ?: null,
                        'dob_precision' => $validated['relNewDob'] ? 'exact' : 'unknown',
                        'is_living' => $validated['relNewIsLiving'],
                        'dod' => $validated['relNewIsLiving'] ? null : ($validated['relNewDod'] ?: null),
                        'created_by' => Auth::id(),
                    ]);

                    app(PageEditorService::class)->grantOwner($other, Auth::user());
                } else {
                    $other = Person::findOrFail($validated['relExistingPersonId']);
                }

                match ($this->relType) {
                    'parent' => Relationship::create([
                        'person_a_id' => $other->id,
                        'person_b_id' => $this->person->id,
                        'type' => 'parent_child',
                    ]),
                    'child' => Relationship::create([
                        'person_a_id' => $this->person->id,
                        'person_b_id' => $other->id,
                        'type' => 'parent_child',
                    ]),
                    'spouse' => Relationship::create([
                        'person_a_id' => $this->person->id,
                        'person_b_id' => $other->id,
                        'type' => 'spouse',
                        'status' => $validated['relSpouseStatus'],
                    ]),
                    'sibling' => $relationships->addSibling($this->person, $other),
                };

                if ($this->relType === 'spouse') {
                    foreach (Person::query()->whereIn('id', $this->relAlsoParentOfChildIds)->get() as $child) {
                        $relationships->linkParentChildIfMissing($other, $child);
                    }
                } elseif ($this->relType === 'child') {
                    foreach (Person::query()->whereIn('id', $this->relAlsoCoParentIds)->get() as $spouse) {
                        $relationships->linkParentChildIfMissing($spouse, $other);
                    }
                } elseif ($this->relType === 'sibling') {
                    foreach (Person::query()->whereIn('id', $this->relAlsoSiblingParentIds)->get() as $parent) {
                        $relationships->linkParentChildIfMissing($parent, $other);
                    }
                }
            });
        } catch (QueryException) {
            Flux::toast(variant: 'danger', text: __('That relationship already exists.'));

            return;
        }

        $this->reset(['relExistingPersonId', 'relNewFirstName', 'relNewMiddleName', 'relNewLastName', 'relNewDob', 'relNewIsLiving', 'relNewDod']);
        unset($this->relationshipRows, $this->candidatePeople, $this->candidateStepchildren, $this->candidateCoParents, $this->existingParents, $this->familyTreeData);
        $this->seedAlsoLinkSuggestions();

        Flux::toast(variant: 'success', text: __('Relationship added.'));

        $this->dispatch('family-widget-updated', data: FamilyTreeSerializer::toChartData());
    }

    public function removeRelationship(int $relationshipId): void
    {
        Gate::authorize('update', $this->person);

        Relationship::query()
            ->where(fn ($query) => $query
                ->where('person_a_id', $this->person->id)
                ->orWhere('person_b_id', $this->person->id))
            ->findOrFail($relationshipId)
            ->delete();

        unset($this->relationshipRows, $this->candidatePeople, $this->existingParents, $this->familyTreeData);

        Flux::toast(variant: 'success', text: __('Relationship removed.'));

        $this->dispatch('family-widget-updated', data: FamilyTreeSerializer::toChartData());
    }

    public function addEditor(): void
    {
        Gate::authorize('update', $this->person);

        $validated = $this->validate(['newEditorUserId' => ['required', 'exists:users,id']]);

        $user = User::findOrFail($validated['newEditorUserId']);
        app(PageEditorService::class)->addCoEditor($this->person, $user);

        $this->reset('newEditorUserId');
        unset($this->editors, $this->candidateUsers);

        Flux::toast(variant: 'success', text: __('Editor added.'));
    }

    public function removeEditor(int $userId): void
    {
        Gate::authorize('update', $this->person);

        try {
            app(PageEditorService::class)->removeEditor($this->person, User::findOrFail($userId));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->editors, $this->candidateUsers);

        Flux::toast(variant: 'success', text: __('Editor removed.'));
    }

    public function rollback(int $revisionId): void
    {
        Gate::authorize('update', $this->person);

        $revision = $this->person->revisions()->findOrFail($revisionId);
        app(RevisionService::class)->rollback($this->person, $revision, Auth::user());

        Flux::toast(variant: 'success', text: __('Rolled back.'));
    }
}; ?>

<section class="w-full max-w-3xl">
    <div class="flex items-start gap-4">
        <x-person-avatar :person="$person" size="xl" />

        <div class="min-w-0 flex-1">
            <flux:heading level="1">{{ $person->fullName() }}</flux:heading>
            @if ($this->relationshipToViewer)
                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ ucfirst($this->relationshipToViewer) }}</flux:text>
            @endif
            <flux:text class="text-zinc-500">
                {{ $person->is_living ? __('Living') : __('Deceased') }}
                @if ($person->dob)
                    &middot; {{ __('Born') }} {{ $person->dob->format('F j, Y') }}
                    @if ($person->birth_city)
                        {{ __('in') }} {{ $person->birth_city }}
                    @endif
                @elseif ($person->birth_city)
                    &middot; {{ __('Born in') }} {{ $person->birth_city }}
                @endif
                @if (! $person->is_living && $person->dod)
                    &middot; {{ __('Died') }} {{ $person->dod->format('F j, Y') }}
                    @if ($person->death_city)
                        {{ __('in') }} {{ $person->death_city }}
                    @endif
                @elseif (! $person->is_living && $person->death_city)
                    &middot; {{ __('Died in') }} {{ $person->death_city }}
                @endif
            </flux:text>
            <div class="mt-1">
                <x-person-account-badges :person="$person" />
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            @if ($this->isSelf && ! $person->hasConsented())
                <flux:button :href="route('people.enrich', $person)" wire:navigate size="sm" variant="primary">
                    {{ __('Add my details') }}
                </flux:button>
            @elseif ($this->isSelf)
                <flux:button :href="route('people.enrich', $person)" wire:navigate size="sm">
                    {{ __('Edit my details') }}
                </flux:button>
            @elseif ($this->canManageEnrichment)
                <flux:button :href="route('people.enrich', $person)" wire:navigate size="sm">
                    {{ __('Memorial photo & details') }}
                </flux:button>
            @endif

            @if ($this->canEdit)
                <flux:button :href="route('people.edit', $person)" wire:navigate size="sm">
                    {{ __('Edit') }}
                </flux:button>
                <flux:button :href="route('people.suggestions', $person)" wire:navigate size="sm">
                    {{ __('Suggestions') }}
                    @if ($this->pendingSuggestionCount > 0)
                        <flux:badge size="sm" color="amber">{{ $this->pendingSuggestionCount }}</flux:badge>
                    @endif
                </flux:button>
            @else
                <flux:button :href="route('people.suggest', $person)" wire:navigate size="sm">
                    {{ __('Suggest an edit') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($this->isSelf && ! $person->hasConsented())
        <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
            <flux:text class="text-amber-900 dark:text-amber-200">
                {{ __('Only your name, dob, and relationships are shown by default. You can choose to add a photo, contact info, and social links — nobody else can add these for you.') }}
            </flux:text>
        </div>
    @elseif (! $this->isSelf && ! $person->hasAccount())
        <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('Only the family member themself can add profile pictures and contact information — that\'s why it\'s sparse here.') }}
            </flux:text>
        </div>
    @endif

    <div class="mt-8 flex flex-col gap-8">
        <div
            x-data
            x-on:family-widget-updated.window="window.updatePersonFamilyWidget($refs.familyWidget, $event.detail.data)"
        >
            <div class="flex items-center justify-between">
                <flux:heading level="2">{{ __('Family') }}</flux:heading>
                <flux:button :href="route('tree.index', ['person' => $person->id])" wire:navigate size="sm">
                    {{ __('View in family tree') }}
                </flux:button>
            </div>

            <div
                wire:ignore
                class="mt-2 h-96 w-full rounded-lg border border-zinc-200 dark:border-zinc-700"
                x-init="initPersonFamilyWidget($refs.familyWidget, @js($this->familyTreeData), @js($person->id))"
            >
                <div x-ref="familyWidget" class="h-full w-full"></div>
            </div>

            @if ($this->canEdit)
                <flux:modal.trigger name="add-relationship">
                    <flux:button size="sm" variant="primary" class="mt-3">
                        {{ __('Add a relationship') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif

            <button type="button" wire:click="$toggle('showManageRelationships')" class="mt-3 flex w-full items-center justify-between text-left">
                <flux:heading level="2" size="sm">{{ __('Manage relationships') }}</flux:heading>
                <flux:icon.chevron-down class="size-4 text-zinc-400 {{ $showManageRelationships ? 'rotate-180' : '' }}" />
            </button>

            @if ($showManageRelationships)
                <div class="mt-2 space-y-2">
                    @forelse ($this->relationshipRows as $row)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-2 dark:border-zinc-700" wire:key="rel-{{ $row['id'] }}">
                            <div class="flex min-w-0 items-center gap-2">
                                <flux:badge size="sm">{{ $row['label'] }}</flux:badge>
                                <a href="{{ route('people.show', $row['other']) }}" wire:navigate class="truncate text-sm hover:underline">
                                    {{ $row['other']->fullName() }}
                                </a>
                            </div>
                            @if ($this->canEdit)
                                <flux:button wire:click="removeRelationship({{ $row['id'] }})" size="sm" variant="ghost" icon="x-mark" />
                            @endif
                        </div>
                    @empty
                        <flux:text class="text-zinc-500">{{ __('No relationships recorded yet.') }}</flux:text>
                    @endforelse

                    @foreach ($this->derivedOnlySiblings as $sibling)
                        <div class="flex items-center justify-between rounded-lg border border-dashed border-zinc-300 p-2 dark:border-zinc-600" wire:key="sibling-{{ $sibling->id }}">
                            <div class="flex min-w-0 items-center gap-2">
                                <flux:badge size="sm">{{ __('Sibling') }}</flux:badge>
                                <a href="{{ route('people.show', $sibling) }}" wire:navigate class="truncate text-sm hover:underline">{{ $sibling->fullName() }}</a>
                            </div>
                            <flux:text class="shrink-0 text-xs text-zinc-500">{{ __('via shared parent') }}</flux:text>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <flux:heading level="2">{{ __('About') }}</flux:heading>
            <div class="prose prose-zinc dark:prose-invert mt-2 max-w-none">
                @if ($person->bio)
                    {!! $person->bio !!}
                @else
                    <flux:text class="text-zinc-500">{{ __('No bio yet.') }}</flux:text>
                @endif
            </div>

            @if ($person->canShowEnrichment() && ($person->address || $person->phone || $person->contact_email || $person->social_links))
                <flux:heading level="2" class="mt-8">{{ __('Contact') }}</flux:heading>
                <dl class="mt-2 space-y-1">
                    @if ($person->contact_email)
                        <div><flux:text class="text-zinc-500">{{ __('Email') }}:</flux:text> {{ $person->contact_email }}</div>
                    @endif
                    @if ($person->phone)
                        <div><flux:text class="text-zinc-500">{{ __('Phone') }}:</flux:text> {{ $person->phone }}</div>
                    @endif
                    @if ($person->address)
                        <div><flux:text class="text-zinc-500">{{ __('Address') }}:</flux:text> {{ $person->address }}</div>
                    @endif
                    @foreach ($person->social_links ?? [] as $link)
                        <div><a href="{{ $link }}" class="text-blue-600 hover:underline dark:text-blue-400" target="_blank" rel="noopener">{{ $link }}</a></div>
                    @endforeach
                </dl>
            @endif

            @if ($this->canEdit)
                <flux:separator class="my-8" />

                <button type="button" wire:click="$toggle('showEditors')" class="flex w-full items-center justify-between text-left">
                    <flux:heading level="2">{{ __('Editors') }} ({{ $this->editors->count() }})</flux:heading>
                    <flux:icon.chevron-down class="size-4 text-zinc-400 {{ $showEditors ? 'rotate-180' : '' }}" />
                </button>

                @if ($showEditors)
                    <div class="mt-3 space-y-2">
                        @foreach ($this->editors as $editor)
                            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="editor-{{ $editor->id }}">
                                <div>
                                    <flux:text>{{ $editor->user->name }}</flux:text>
                                    <flux:text class="text-xs text-zinc-500">{{ $editor->role === 'owner' ? __('Owner') : __('Co-editor') }}</flux:text>
                                </div>
                                <flux:button wire:click="removeEditor({{ $editor->user_id }})" size="sm" variant="ghost" icon="x-mark" />
                            </div>
                        @endforeach
                    </div>

                    <form wire:submit="addEditor" class="mt-3 flex items-end gap-2">
                        <flux:select variant="combobox" wire:model="newEditorUserId" :label="__('Add a co-editor')" :placeholder="__('Search members…')" clearable class="flex-1">
                            @foreach ($this->candidateUsers as $candidate)
                                <flux:select.option value="{{ $candidate->id }}">{{ $candidate->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" size="sm">{{ __('Add') }}</flux:button>
                    </form>
                @endif

                <flux:separator class="my-8" />

                <button type="button" wire:click="$toggle('showHistory')" class="flex w-full items-center justify-between text-left">
                    <flux:heading level="2">{{ __('History') }}</flux:heading>
                    <flux:icon.chevron-down class="size-4 text-zinc-400 {{ $showHistory ? 'rotate-180' : '' }}" />
                </button>

                @if ($showHistory)
                    <div class="mt-3 space-y-2">
                        @forelse ($person->revisions as $revision)
                            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="revision-{{ $revision->id }}">
                                <div>
                                    <flux:text>{{ $revision->user->name }}</flux:text>
                                    <flux:text class="text-xs text-zinc-500">{{ $revision->created_at->format('F j, Y g:ia') }}</flux:text>
                                </div>
                                @if (! $loop->first)
                                    <flux:button wire:click="rollback({{ $revision->id }})" size="sm">{{ __('Roll back to this version') }}</flux:button>
                                @else
                                    <flux:badge>{{ __('Current') }}</flux:badge>
                                @endif
                            </div>
                        @empty
                            <flux:text class="text-zinc-500">{{ __('No revisions yet.') }}</flux:text>
                        @endforelse
                    </div>
                @endif
            @endif

            @if ($this->relatedStories->isNotEmpty())
                <flux:heading level="2" class="mt-8">{{ __('Stories') }}</flux:heading>
                <div class="mt-2 space-y-1">
                    @foreach ($this->relatedStories as $story)
                        <a href="{{ route('stories.show', $story) }}" wire:navigate class="block text-sm hover:underline">{{ $story->title }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($this->canEdit)
                <flux:modal name="add-relationship" class="w-96">
                    <flux:heading level="2" size="sm">{{ __('Add a relationship') }}</flux:heading>

                    <form wire:submit="addRelationship" class="mt-3 flex flex-col gap-3">
                        <flux:radio.group wire:model.live="relType">
                            <flux:radio value="parent" label="{{ __('Parent') }}" />
                            <flux:radio value="child" label="{{ __('Child') }}" />
                            <flux:radio value="spouse" label="{{ __('Spouse') }}" />
                            <flux:radio value="sibling" label="{{ __('Sibling') }}" />
                        </flux:radio.group>

                        <div x-show="$wire.relType === 'spouse'">
                            <flux:select wire:model="relSpouseStatus">
                                <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                                <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                                <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
                            </flux:select>

                            <x-relationship-pills
                                :label="__('Also mark as parent of')"
                                :hint="__('Click × to remove anyone who doesn\'t apply.')"
                                property="relAlsoParentOfChildIds"
                                :candidates="$this->candidateStepchildren"
                                :selected-ids="$relAlsoParentOfChildIds"
                            />
                        </div>

                        <div x-show="$wire.relType === 'child'">
                            <x-relationship-pills
                                :label="__('Also mark as parent')"
                                :hint="__('Click × to remove anyone who doesn\'t apply.')"
                                property="relAlsoCoParentIds"
                                :candidates="$this->candidateCoParents"
                                :selected-ids="$relAlsoCoParentIds"
                            />
                        </div>

                        <div x-show="$wire.relType === 'sibling'">
                            <x-relationship-pills
                                :label="__('Also link as parent')"
                                :hint="__('Click × to remove anyone who doesn\'t apply.')"
                                property="relAlsoSiblingParentIds"
                                :candidates="$this->existingParents"
                                :selected-ids="$relAlsoSiblingParentIds"
                            />

                            @if ($person->siblings()->isNotEmpty())
                                <div class="rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ __('They\'ll also become a sibling of:') }}
                                        {{ $person->siblings()->map->fullName()->join(', ') }}
                                    </flux:text>
                                </div>
                            @endif
                        </div>

                        <flux:separator />

                        <flux:radio.group wire:model="relMode">
                            <flux:radio value="existing" label="{{ __('Existing person') }}" />
                            <flux:radio value="new" label="{{ __('New person') }}" />
                        </flux:radio.group>

                        <div x-show="$wire.relMode === 'existing'">
                            <flux:select variant="combobox" wire:model="relExistingPersonId" :placeholder="__('Search people…')" clearable>
                                @foreach ($this->candidatePeople as $candidate)
                                    <flux:select.option value="{{ $candidate->id }}">{{ $candidate->fullName() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div x-show="$wire.relMode === 'new'" class="flex flex-col gap-3">
                            <flux:input wire:model="relNewFirstName" :placeholder="__('First name')" />
                            <flux:input wire:model="relNewMiddleName" :placeholder="__('Middle name')" />
                            <flux:input wire:model="relNewLastName" :placeholder="__('Last name')" />
                            <flux:input wire:model="relNewDob" type="date" :placeholder="__('Date of birth')" />
                            <flux:checkbox wire:model="relNewIsLiving" :label="__('Living')" />
                            <div x-show="! $wire.relNewIsLiving">
                                <flux:input wire:model="relNewDod" type="date" :placeholder="__('Date of death')" />
                            </div>
                        </div>

                        <flux:button type="submit" variant="primary" size="sm">{{ __('Add relationship') }}</flux:button>
                    </form>
                </flux:modal>
            @endif
        </div>
    </div>
</section>
