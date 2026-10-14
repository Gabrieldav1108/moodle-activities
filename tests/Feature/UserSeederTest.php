<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Independent of the local .env, which holds the real owner.
        config(['owner.name' => 'Dashboard Owner', 'owner.email' => 'owner@example.com', 'owner.password' => '']);
    }

    public function test_creates_a_verified_owner(): void
    {
        $this->seed(UserSeeder::class);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('Dashboard Owner', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_keeps_the_password_of_an_existing_user(): void
    {
        User::factory()->unverified()->create(['email' => 'owner@example.com', 'password' => 'my-own-password']);

        $this->seed(UserSeeder::class);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('my-own-password', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, User::query()->count());
    }
}
