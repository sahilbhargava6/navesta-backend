<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'navesta:set-admin-password
        {email : Email of the admin account}
        {password : New password (min 12 chars)}';

    protected $description = 'Set or reset the password for an admin account';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User {$email} not found.");
            return self::FAILURE;
        }

        $user->forceFill([
            'password_hash' => Hash::make($password),
            'role' => UserRole::Admin,
            'is_verified' => true,
        ])->save();

        $this->info("Password for admin {$email} updated successfully.");

        return self::SUCCESS;
    }
}
