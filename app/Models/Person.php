<?php

namespace App\Models;

use App\Concerns\HasWikiWorkflow;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string|null $preferred_name
 * @property bool $use_preferred_name_everywhere
 * @property Carbon|null $dob
 * @property string $dob_precision
 * @property Carbon|null $dod
 * @property bool $is_living
 * @property string|null $bio
 * @property Carbon|null $consented_at
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $contact_email
 * @property array<int, string>|null $social_links
 * @property int $created_by
 * @property-read User|null $user
 * @property-read User $creator
 */
#[Fillable([
    'first_name', 'middle_name', 'last_name', 'preferred_name', 'use_preferred_name_everywhere', 'dob', 'dob_precision', 'dod', 'is_living', 'bio', 'created_by',
    'consented_at', 'address', 'phone', 'contact_email', 'social_links',
])]
class Person extends Model implements HasMedia
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasWikiWorkflow, InteractsWithMedia;

    /**
     * Eloquent doesn't reload a model's attributes from the DB after insert,
     * so a freshly `create()`d Person without this key explicitly passed
     * would otherwise read as null in-memory even though the DB column
     * default is false — set it here so it's always a real bool.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'use_preferred_name_everywhere' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'dod' => 'date',
            'is_living' => 'boolean',
            'use_preferred_name_everywhere' => 'boolean',
            'consented_at' => 'datetime',
            'social_links' => 'array',
        ];
    }

    /**
     * A single self-uploaded profile photo — see manageEnrichment on PersonPolicy;
     * only the linked user may ever add or replace it.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    /**
     * A short-lived signed URL rather than a permanent public one — this app
     * is invite-only and photos shouldn't be reachable by anyone who merely
     * guesses or leaks a URL, on R2 or otherwise.
     */
    public function photoUrl(): ?string
    {
        if (! $this->getFirstMedia('photo')) {
            return null;
        }

        return $this->getFirstTemporaryUrl(now()->addHour(), 'photo');
    }

    public function hasConsented(): bool
    {
        return $this->consented_at !== null;
    }

    /**
     * Whether enrichment fields (photo, contact info) may be shown/added at all.
     * Living people must explicitly consent; the deceased have no one to withhold
     * consent, so editors may add memorial photos/details without it.
     */
    public function canShowEnrichment(): bool
    {
        return $this->hasConsented() || ! $this->is_living;
    }

    /**
     * @return Collection<int, Person>
     */
    public function parents(): Collection
    {
        return Relationship::query()
            ->where('person_b_id', $this->id)
            ->where('type', 'parent_child')
            ->with('personA')
            ->get()
            ->pluck('personA');
    }

    /**
     * @return Collection<int, Person>
     */
    public function children(): Collection
    {
        return Relationship::query()
            ->where('person_a_id', $this->id)
            ->where('type', 'parent_child')
            ->with('personB')
            ->get()
            ->pluck('personB');
    }

    /**
     * @return Collection<int, Person>
     */
    public function spouses(): Collection
    {
        return Relationship::query()
            ->where('type', 'spouse')
            ->where(fn ($query) => $query
                ->where('person_a_id', $this->id)
                ->orWhere('person_b_id', $this->id))
            ->with(['personA', 'personB'])
            ->get()
            ->map(fn (Relationship $relationship) => $relationship->person_a_id === $this->id
                ? $relationship->personB
                : $relationship->personA);
    }

    /**
     * A person's siblings: primarily explicit "sibling" relationships (stored
     * so they're visible and removable like any other relationship), plus a
     * fallback derivation from sharing a recorded parent — for anyone who
     * shares a parent without (yet) having an explicit sibling row, e.g. data
     * added outside the normal "Sibling" relationship flow.
     *
     * @return Collection<int, Person>
     */
    public function siblings(): Collection
    {
        $explicit = Relationship::query()
            ->where('type', 'sibling')
            ->where(fn ($query) => $query
                ->where('person_a_id', $this->id)
                ->orWhere('person_b_id', $this->id))
            ->with(['personA', 'personB'])
            ->get()
            ->map(fn (Relationship $relationship) => $relationship->person_a_id === $this->id
                ? $relationship->personB
                : $relationship->personA);

        $parentIds = $this->parents()->pluck('id');

        $derived = $parentIds->isEmpty() ? collect() : Relationship::query()
            ->whereIn('person_a_id', $parentIds)
            ->where('type', 'parent_child')
            ->where('person_b_id', '!=', $this->id)
            ->with('personB')
            ->get()
            ->pluck('personB');

        return $explicit->merge($derived)->unique('id')->values();
    }

    /**
     * The name shown throughout the app. Uses the "goes by" name in place of
     * the full legal name when the person (or their editor) has opted into
     * that via `use_preferred_name_everywhere` — otherwise the legal name.
     */
    public function fullName(): string
    {
        if ($this->use_preferred_name_everywhere && $this->preferred_name) {
            return $this->preferred_name;
        }

        return $this->legalName();
    }

    /**
     * The name on record — first, middle, and last — regardless of any
     * "goes by" preference. Used on the edit form itself, where both names
     * need to be visible and distinct.
     */
    public function legalName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    public function wikiTitle(): string
    {
        return $this->fullName();
    }

    public function wikiShowUrl(): string
    {
        return route('people.show', $this);
    }

    public function wikiSuggestionsUrl(): string
    {
        return route('people.suggestions', $this);
    }

    /**
     * The user account linked to this person, if they've joined.
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Whether this person has joined and linked a user account — shown on
     * their page so it's clear why enrichment fields (photo, contact info)
     * are empty for people who haven't.
     */
    public function hasAccount(): bool
    {
        return $this->user !== null;
    }

    /**
     * Whether the person themself is listed as an editor of their own page —
     * normally true once they join (see PageEditorService::grantOwner), but
     * shown explicitly since it can change if editors are reassigned.
     */
    public function isEditorOfOwnPage(): bool
    {
        return $this->user !== null && $this->isEditor($this->user);
    }

    /**
     * The user who created this person's page.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Story, $this>
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_person');
    }
}
