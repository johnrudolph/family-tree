<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\User;
use App\Services\PageEditorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

#[Signature('app:bootstrap-admin')]
#[Description('Create the first admin user and their linked person, for a fresh install with registration disabled.')]
class BootstrapAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->error('An admin already exists. Use the in-app invite flow to add more members.');

            return self::FAILURE;
        }

        $firstName = $this->ask('First name');
        $lastName = $this->ask('Last name');
        $email = $this->ask('Email address');
        $password = $this->secret('Password');

        try {
            validator([
                'email' => $email,
                'password' => $password,
            ], [
                'email' => ['required', 'email'],
                'password' => ['required', Password::defaults()],
            ])->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($firstName, $lastName, $email, $password) {
            $user = User::create([
                'name' => trim("{$firstName} {$lastName}"),
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]);

            $person = Person::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'is_living' => true,
                'created_by' => $user->id,
            ]);

            $user->update(['person_id' => $person->id]);

            app(PageEditorService::class)->grantOwner($person, $user);
        });

        $this->info("Admin account created for {$email}. Log in and set up two-factor authentication to continue — it's required for every account.");

        return self::SUCCESS;
    }
}
