<?php

namespace App\Console\Commands\Api;

use App\Models\ApiToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('api-token:issue {name}')]
#[Description('Issue a new bearer token for the read-only model API. Prints the plaintext once — it is never stored or shown again.')]
class IssueApiToken extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = ApiToken::issue($this->argument('name'));

        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->warn('Copy this now — it will not be shown again.');

        return self::SUCCESS;
    }
}
