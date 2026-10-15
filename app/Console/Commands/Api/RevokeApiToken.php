<?php

namespace App\Console\Commands\Api;

use App\Models\ApiToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('api-token:revoke {id}')]
#[Description('Permanently revoke a read-only API token by its ID (see api-token:list).')]
class RevokeApiToken extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = ApiToken::query()->find($this->argument('id'));

        if (! $token) {
            $this->error('No token with that ID.');

            return self::FAILURE;
        }

        $name = $token->name;
        $token->delete();

        $this->info("Revoked \"{$name}\".");

        return self::SUCCESS;
    }
}
