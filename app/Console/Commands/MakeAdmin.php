<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class MakeAdmin extends Command
{
    protected $signature = 'navesta:make-admin
        {email : Email of the admin (an existing account is promoted)}
        {--name= : Name for a new account}';

    protected $description = 'Create an admin account for /admin, or promote an existing user';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill(['role' => UserRole::Admin])->save();
            app(AuditLogger::class)->record('admin.granted', $user, ['via' => 'navesta:make-admin']);
            $this->info("{$email} is now an admin. They must set up an authenticator app at first sign-in.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'min:2', 'max:120'],
                'password' => ['required', Password::min(12)->letters()->numbers()->symbols()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'is_verified' => true,
            'reminders_enabled' => false,
        ]);
        $user->role = UserRole::Admin;
        $user->save();
        app(AuditLogger::class)->record('admin.granted', $user, ['via' => 'navesta:make-admin', 'new_account' => true]);

        $this->info("Admin {$email} created. Sign in at ".url('/admin').' and set up an authenticator app.');

        return self::SUCCESS;
    }
}
