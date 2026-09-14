<?php

namespace App\Models;

use Database\Factories\RelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $person_a_id
 * @property int $person_b_id
 * @property string $type
 * @property string|null $status
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property-read Person $personA
 * @property-read Person $personB
 */
#[Fillable(['person_a_id', 'person_b_id', 'type', 'status', 'started_at', 'ended_at'])]
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
}
