<?php

namespace App\Models;

use App\Support\FamilyTreeSerializer;
use Database\Factories\RelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $person_a_id
 * @property int $person_b_id
 * @property string $type
 * @property string|null $status
 * @property int|null $location_id
 * @property Carbon|null $started_at
 * @property string|null $started_at_precision
 * @property Carbon|null $ended_at
 * @property string|null $ended_at_precision
 * @property-read Person $personA
 * @property-read Person $personB
 * @property-read Location|null $location
 * @property-read Collection<int, Photo> $photos
 */
#[Fillable(['person_a_id', 'person_b_id', 'type', 'status', 'location_id', 'started_at', 'started_at_precision', 'ended_at', 'ended_at_precision'])]
class Relationship extends Model
{
    /** @use HasFactory<RelationshipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FamilyTreeSerializer::forgetCache());
        static::deleted(fn () => FamilyTreeSerializer::forgetCache());
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function personA(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_a_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function personB(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_b_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Photo, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'relationship_photo')->orderBy('photos.created_at');
    }
}
