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
    'first_name', 'middle_name', 'last_name', 'preferred_name', 'dob', 'dob_precision', 'dod', 'is_living', 'bio', 'created_by',
    'consented_at', 'address', 'phone', 'contact_email', 'social_links',
])]
class Person extends Model implements HasMedia
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasWikiWorkflow, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'dod' => 'date',
            'is_living' => 'boolean',
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

    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo') ?: null;
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

    public function fullName(): string
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
