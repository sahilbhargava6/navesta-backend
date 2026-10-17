<?php

namespace Tests\Feature;

use App\Enums\BtStatus;
use App\Enums\UserRole;
use App\Filament\Resources\BtApplications\Pages\ListBtApplications;
use App\Filament\Resources\BtApplications\Pages\ViewBtApplication;
use App\Filament\Resources\Lenders\Pages\EditLender;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\BtApplication;
use App\Models\Lender;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\LenderSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function application(User $customer, BtStatus $status = BtStatus::Submitted): BtApplication
    {
        $card = $customer->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 25000000, 'outstanding_paise' => 12500000, 'apr_bps' => 4200]);

        return $customer->btApplications()->create([
            'lender_id' => Lender::first()->id,
            'source_cc_id' => $card->id,
            'outstanding_paise' => 12500000,
            'current_rate_bps' => 4200,
            'new_rate_bps' => 850,
            'requested_tenure_months' => 24,
            'status' => $status,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LenderSeeder::class);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customers_cannot_open_the_panel(): void
    {
        $this->actingAs($this->createUser())->get('/admin')->assertForbidden();
    }

    public function test_admins_must_set_up_two_factor_first(): void
    {
        $admin = $this->createUser();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createUser();
        $this->application($customer);

        foreach (['/admin', '/admin/users', "/admin/users/{$customer->id}", '/admin/lenders', '/admin/bt-applications', '/admin/posts', '/admin/posts/create'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_reviews_an_application(): void
    {
        $this->actingAs($this->createAdmin());
        Filament::setCurrentPanel('admin');
        $application = $this->application($this->createUser());

        Livewire::test(ListBtApplications::class)->assertCanSeeTableRecords([$application]);

        Livewire::test(ViewBtApplication::class, ['record' => $application->id])
            ->assertActionVisible('approve')
            ->assertActionHidden('disburse')
            ->callAction('approve');

        $this->assertSame(BtStatus::Approved, $application->fresh()->status);

        Livewire::test(ViewBtApplication::class, ['record' => $application->id])
            ->assertActionHidden('approve')
            ->callAction('disburse');

        $this->assertSame(BtStatus::Disbursed, $application->fresh()->status);
    }

    public function test_lender_terms_are_edited_in_rupees_and_percent(): void
    {
        $this->actingAs($this->createAdmin());
        Filament::setCurrentPanel('admin');
        $lender = Lender::where('name', 'HDFC Bank')->first();

        Livewire::test(EditLender::class, ['record' => $lender->id])
            ->assertSchemaStateSet(['min_rate_bps' => 8.5, 'max_loan_amount_paise' => 5000000])
            ->fillForm(['min_rate_bps' => 8.25, 'max_loan_amount_paise' => 6000000, 'min_credit_score' => 740])
            ->call('save')
            ->assertHasNoFormErrors();

        $lender->refresh();
        $this->assertSame(825, $lender->min_rate_bps);
        $this->assertSame(600000000, $lender->max_loan_amount_paise);
        $this->assertSame(740, $lender->min_credit_score);
    }

    public function test_admin_publishes_a_post(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Five ways to cut card interest',
                'slug' => 'five-ways-to-cut-card-interest',
                'body' => 'Pay more than the minimum.',
                'status' => 'published',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::firstWhere('slug', 'five-ways-to-cut-card-interest');
        $this->assertTrue($post->author->is($admin));
        $this->getJson('/api/v1/posts/five-ways-to-cut-card-interest')->assertOk();
    }

    public function test_make_admin_promotes_an_existing_user(): void
    {
        $user = $this->createUser(['email' => 'ops@navesta.in']);

        $this->artisan('navesta:make-admin', ['email' => 'ops@navesta.in'])->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_role_cannot_be_set_through_the_api(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'secret123', 'role' => 'admin', 'acceptTerms' => true,
        ])->assertCreated();

        $this->assertSame(UserRole::Customer, User::firstWhere('email', 'sneaky@example.com')->role);
    }
}
