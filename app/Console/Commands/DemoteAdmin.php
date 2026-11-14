<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class DemoteAdmin extends Command
{
    protected $signature = 'navesta:demote-admin
        {email : Email of the user to revert to standard customer role}';

    protected $description = 'Revert an admin user back to a standard customer';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");
            return self::FAILURE;
        }

        $user->forceFill(['role' => UserRole::Customer])->save();
        app(AuditLogger::class)->record('admin.revoked', $user, ['via' => 'navesta:demote-admin']);
        $this->info("{$email} has been reverted to a standard customer role.");

        return self::SUCCESS;
    }
}
