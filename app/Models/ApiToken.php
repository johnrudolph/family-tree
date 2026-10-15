<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bearer token for the read-only model API (routes/api.php) — this app
 * has exactly one real "tenant," so there's no per-user scoping, just a
 * named credential that is or isn't valid. Only the hash is ever stored;
 * the plaintext token is shown once, at creation, and never again.
 *
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 */
#[Fillable(['name', 'token_hash'])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    /**
     * Generates a new token, persists only its hash, and returns the
     * plaintext — the only moment it's ever available in full.
     */
    public static function issue(string $name): string
    {
        $plaintext = Str::random(64);

        static::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    public static function findByPlaintext(string $plaintext): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $plaintext))->first();
    }
}
