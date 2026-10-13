<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Create (or update) the owner account, already verified.
     *
     * The password comes from SEED_USER_PASSWORD. Without it, an existing user keeps
     * their password and a new one gets a random password printed to the console.
     */
    public function run(): void
    {
        $user = User::query()->firstOrNew(['email' => env('SEED_USER_EMAIL', 'owner@example.com')]);
        $user->name = env('SEED_USER_NAME', 'Dashboard Owner');

        $password = env('SEED_USER_PASSWORD');
        if (! $user->exists && blank($password)) {
            $password = Str::password(16);
            $this->command?->warn("Generated password for {$user->email}: {$password}");
        }
        if (filled($password)) {
            $user->password = $password;
        }

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->command?->info("User {$user->email} ready.");
    }
}
