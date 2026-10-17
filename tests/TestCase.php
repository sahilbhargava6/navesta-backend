<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests wipe the database (RefreshDatabase). Refuse to run against
     * anything but SQLite or a database whose name ends in "_test".
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against [{$connection}:{$database}]. Use SQLite or a *_test database.");
        }

        return $app;
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create([
            'name' => 'Asha Rao',
            'email' => 'asha'.uniqid().'@example.com',
            'phone' => '+919800000000',
            'password_hash' => Hash::make('secret123'),
            'employment_sector' => 'pvt',
            'reminders_enabled' => true,
            ...$attributes,
        ]);
    }

    /**
     * An admin who has finished two-factor setup, so the panel lets them in.
     */
    protected function createAdmin(): User
    {
        $admin = $this->createUser(['name' => 'Admin User']);
        $admin->role = UserRole::Admin;
        $admin->app_authentication_secret = 'JBSWY3DPEHPK3PXP';
        $admin->save();

        return $admin;
    }

    /**
     * Bearer header with a real Sanctum token for the user.
     */
    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }
}
