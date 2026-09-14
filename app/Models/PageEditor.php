<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $editable_type
 * @property int $editable_id
 * @property int $user_id
 * @property string $role
 * @property-read User $user
 */
#[Fillable(['editable_type', 'editable_id', 'user_id', 'role'])]
class PageEditor extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function editable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
