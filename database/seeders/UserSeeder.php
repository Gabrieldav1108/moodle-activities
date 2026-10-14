<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Create (or update) the owner account from config/owner.php, already verified.
     *
     * Without SEED_USER_PASSWORD an existing user keeps their password and a new
     * one gets a random password printed to the console.
     */
    public function run(): void
    {
        $user = User::query()->firstOrNew(['email' => Config::string('owner.email')]);
        $user->name = Config::string('owner.name');

        $password = Config::string('owner.password');
        if (! $user->exists && $password === '') {
            $password = Str::password(16);
            $this->command->warn("Generated password for {$user->email}: {$password}");
        }
        if ($password !== '') {
            $user->password = $password;
        }

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->command->info("User {$user->email} ready.");
    }
}
