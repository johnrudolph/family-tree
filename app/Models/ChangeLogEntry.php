<?php

namespace App\Models;

use Database\Factories\ChangeLogEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single, plain-language release note — one short sentence describing a
 * user-facing change, shown on the Change Log page in the order it landed
 * on main.
 *
 * @property int $id
 * @property string $description
 * @property Carbon $merged_at
 */
#[Fillable(['description', 'merged_at'])]
class ChangeLogEntry extends Model
{
    /** @use HasFactory<ChangeLogEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'merged_at' => 'datetime',
        ];
    }
}
