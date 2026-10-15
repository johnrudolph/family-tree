<?php

namespace App\Console\Commands\Api;

use App\Models\ApiToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('api-token:list')]
#[Description('List every issued read-only API token (never the plaintext — only the name and last-used time).')]
class ListApiTokens extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tokens = ApiToken::query()->orderByDesc('created_at')->get();

        if ($tokens->isEmpty()) {
            $this->info('No tokens issued yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Last used', 'Created'],
            $tokens->map(fn (ApiToken $token) => [
                $token->id,
                $token->name,
                $token->last_used_at?->diffForHumans() ?? 'never',
                $token->created_at->toDateString(),
            ]),
        );

        return self::SUCCESS;
    }
}
