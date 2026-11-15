<?php

namespace Tests\Feature;

use App\Console\Commands\DemoAccounts;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Database\Seeders\LenderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_logins_are_created_with_sample_data_and_reset_on_each_run(): void
    {
        $this->seed(LenderSeeder::class);

        $this->artisan('navesta:demo-accounts')->assertSuccessful();
        $customer = User::where('email', DemoAccounts::CUSTOMER_EMAIL)->firstOrFail();
        $this->assertSame(UserRole::Admin, User::where('email', DemoAccounts::ADMIN_EMAIL)->firstOrFail()->role);
        $this->assertSame(2, $customer->creditCards()->count());
        $this->assertSame(1, $customer->loans()->count());
        $this->assertSame(1, $customer->btApplications()->count());
        $this->assertSame(2, Inquiry::count());

        // Running again replaces everything (no duplicates) with a new password
        Artisan::call('navesta:demo-accounts');
        $output = Artisan::output();
        $this->assertSame(1, User::where('email', DemoAccounts::CUSTOMER_EMAIL)->count());
        $this->assertSame(2, Inquiry::count());

        // The printed customer password signs in
        preg_match('/'.preg_quote(DemoAccounts::CUSTOMER_EMAIL, '/').'\s*\|\s*(\S+)\s*\|/', $output, $m);
        $this->postJson('/api/v1/auth/login', ['email' => DemoAccounts::CUSTOMER_EMAIL, 'password' => $m[1]])->assertOk();

        $this->artisan('navesta:demo-accounts', ['--delete' => true])->assertSuccessful();
        $this->assertSame(0, User::whereIn('email', [DemoAccounts::ADMIN_EMAIL, DemoAccounts::CUSTOMER_EMAIL])->count());
        $this->assertSame(0, Inquiry::count());
    }

    public function test_never_runs_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('navesta:demo-accounts')->assertFailed();
        $this->assertSame(0, User::count());
    }
}
