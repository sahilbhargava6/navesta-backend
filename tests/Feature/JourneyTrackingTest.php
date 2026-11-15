<?php

namespace Tests\Feature;

use App\Filament\Pages\JourneyFunnel;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\JourneyEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Journey tracking: which step of the website journeys people reach, the
 * admin funnel, and each customer's progress for advisors.
 */
class JourneyTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function steps(User $user, string $journey, array $steps, ?string $visitorId = null): void
    {
        foreach ($steps as $step) {
            JourneyEvent::create(['user_id' => $user->id, 'visitor_id' => $visitorId, 'journey' => $journey, 'step' => $step]);
        }
    }

    public function test_visitor_steps_are_recorded_once_and_unknown_steps_ignored(): void
    {
        $visitor = (string) Str::uuid();

        $this->postJson('/api/v1/journey-events', ['visitorId' => $visitor, 'events' => [
            ['journey' => 'signup', 'step' => 'home_visit'],
            ['journey' => 'signup', 'step' => 'home_visit'],        // same step again: counted once
            ['journey' => 'credit_card', 'step' => 'made_up_step'],  // not in the catalogue
            ['journey' => 'life_events', 'step' => 'planner', 'detail' => 'wedding'],
        ]])->assertStatus(202)->assertJsonPath('data.recorded', 2);

        // A refresh a minute later doesn't count again
        $this->postJson('/api/v1/journey-events', ['visitorId' => $visitor, 'events' => [['journey' => 'signup', 'step' => 'home_visit']]])
            ->assertJsonPath('data.recorded', 0);

        $this->assertSame(2, JourneyEvent::where('visitor_id', $visitor)->whereNull('user_id')->count());
        $this->assertDatabaseHas('journey_events', ['journey' => 'life_events', 'step' => 'planner', 'detail' => 'wedding']);
    }

    public function test_signed_in_customers_steps_are_linked_to_their_account(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/v1/journey-events', ['events' => [['journey' => 'credit_card', 'step' => 'analysis']]], $this->authHeaders($user))
            ->assertStatus(202)->assertJsonPath('data.recorded', 1);

        $this->assertSame('analysis', $user->journeyEvents()->sole()->step);
    }

    public function test_bad_requests_are_refused(): void
    {
        // Signed out without a browser id
        $this->postJson('/api/v1/journey-events', ['events' => [['journey' => 'signup', 'step' => 'opened']]])->assertStatus(400);
        // Free text in the detail field
        $this->postJson('/api/v1/journey-events', ['visitorId' => (string) Str::uuid(), 'events' => [['journey' => 'life_events', 'step' => 'planner', 'detail' => 'Priya, 9876543210']]])->assertStatus(400);
        // Too many at once
        $this->postJson('/api/v1/journey-events', ['visitorId' => (string) Str::uuid(), 'events' => array_fill(0, 21, ['journey' => 'signup', 'step' => 'opened'])])->assertStatus(400);

        $this->assertSame(0, JourneyEvent::count());
    }

    public function test_admin_funnel_counts_people_per_step_and_names_the_biggest_drop(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->steps($this->createUser(), 'credit_card', ['add_card', 'analysis', 'fixes', 'switch_to_loan', 'offers', 'offers_shown', 'applied']);
        $this->steps($this->createUser(), 'credit_card', ['add_card', 'analysis', 'fixes', 'switch_to_loan', 'offers', 'offers_shown']);
        $this->steps($this->createUser(), 'credit_card', ['add_card', 'analysis']);
        $this->steps($this->createUser(), 'credit_card', ['add_card']);
        // An event older than the period is left out
        (new JourneyEvent)->forceFill(['user_id' => $this->createUser()->id, 'journey' => 'credit_card', 'step' => 'add_card', 'created_at' => now()->subDays(60)])->save();

        $cc = collect(Livewire::test(JourneyFunnel::class)->instance()->funnels())->firstWhere('key', 'credit_card');

        $this->assertSame(4, $cc['start']);
        $this->assertSame([4, 3, 2, 2, 2, 2, 1], array_column($cc['steps'], 'people'));
        $this->assertSame([100, 75, 50, 50, 50, 50, 25], array_column($cc['steps'], 'pct'));
        $this->assertSame(['from' => 'Offers shown', 'to' => 'Applied', 'drop' => 50], $cc['biggestDrop']);

        $this->get('/admin/journey-funnel')->assertOk()->assertSee('Journey funnel')->assertSee('Where your money goes');
    }

    public function test_advisors_see_each_customers_progress_and_can_filter_by_last_step(): void
    {
        $this->actingAs($this->createAdmin());
        Filament::setCurrentPanel('admin');

        $stopped = $this->createUser(['name' => 'Stopped At Offers']);
        $this->steps($stopped, 'credit_card', ['add_card', 'analysis', 'fixes', 'switch_to_loan', 'offers', 'offers_shown']);
        // Browsing elsewhere afterwards doesn't hide that they stopped at the card offers
        $this->steps($stopped, 'life_events', ['hub']);
        $applied = $this->createUser(['name' => 'Applied Already']);
        $this->steps($applied, 'credit_card', ['add_card', 'offers_shown', 'applied']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$stopped, $applied])
            ->assertSee('Opened Life Events')        // latest step, in the "Last step" column
            ->filterTable('stopped_at', 'credit_card.offers_shown')
            ->assertCanSeeTableRecords([$stopped])
            ->assertCanNotSeeTableRecords([$applied]);

        $this->get("/admin/users/{$stopped->id}")
            ->assertOk()
            ->assertSee('Credit card: Offers shown — stopped at step 6 of 7', false)
            ->assertSee('Not allowed'); // no marketing consent: no follow-up calls
    }

    public function test_customers_cannot_open_the_funnel(): void
    {
        $this->actingAs($this->createUser())->get('/admin/journey-funnel')->assertForbidden();
    }

    public function test_progress_is_exported_and_erased_with_the_account_including_pre_sign_in_steps(): void
    {
        $user = $this->createUser();
        $browser = (string) Str::uuid();
        JourneyEvent::create(['visitor_id' => $browser, 'journey' => 'signup', 'step' => 'home_visit']); // before signing in
        $this->steps($user, 'loan', ['details', 'analysis'], $browser);
        $someoneElse = JourneyEvent::create(['visitor_id' => (string) Str::uuid(), 'journey' => 'signup', 'step' => 'home_visit']);
        $headers = $this->authHeaders($user);

        // The export is a downloaded JSON file
        $export = json_decode($this->get('/api/v1/users/me/export', $headers)->assertOk()->streamedContent(), true);
        $this->assertCount(2, $export['journeyProgress']);
        $this->assertSame('details', $export['journeyProgress'][0]['step']);

        $this->deleteJson('/api/v1/users/me', ['password' => 'secret123'], $headers)->assertOk();

        $this->assertSame(0, JourneyEvent::where('visitor_id', $browser)->count());
        $this->assertModelExists($someoneElse);
    }
}
