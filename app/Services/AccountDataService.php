<?php

namespace App\Services;

use App\Http\Resources\BtApplicationResource;
use App\Http\Resources\CreditCardResource;
use App\Http\Resources\LoanResource;
use App\Http\Resources\StatementResource;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\CreditScore;
use App\Models\DebtSnapshot;
use App\Models\JourneyEvent;
use App\Models\User;
use App\Models\UserFootprint;
use Illuminate\Support\Facades\DB;

/**
 * Data-protection requests: export everything held about a customer, or
 * erase it.
 */
class AccountDataService
{
    public function __construct(
        private StatementVault $vault,
        private AuditLogger $audit,
    ) {}

    public function export(User $user): array
    {
        $request = request();

        return [
            'exportedAt' => now()->toJSON(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'phoneVerifiedAt' => $user->phone_verified_at?->toJSON(),
                'propertyStatus' => $user->property_status,
                'employmentSector' => $user->employment_sector,
                'remindersEnabled' => $user->reminders_enabled,
                'twoFactorEnabled' => $user->two_factor_enabled,
                'createdAt' => $user->created_at?->toJSON(),
            ],
            'creditCards' => CreditCardResource::collection($user->creditCards()->get())->resolve($request),
            'loans' => LoanResource::collection($user->loans()->get())->resolve($request),
            'balanceTransferApplications' => BtApplicationResource::collection($user->btApplications()->with('lender')->get())->resolve($request),
            'creditScores' => $user->creditScores()->get()->map(fn (CreditScore $s) => [
                'score' => $s->score,
                'source' => $s->source->value,
                'fetchedAt' => $s->fetched_at->toJSON(),
            ])->all(),
            'debtHistory' => $user->debtSnapshots()->orderBy('snapshot_month')->get()->map(fn (DebtSnapshot $s) => [
                'month' => $s->snapshot_month->format('Y-m'),
                'creditCardPaise' => $s->cc_outstanding_paise,
                'loanPaise' => $s->loan_outstanding_paise,
                'monthlyPaymentsPaise' => $s->total_emi_paise,
            ])->all(),
            'statements' => StatementResource::collection($user->statements()->get())->resolve($request),
            'consents' => $user->consents()->orderBy('created_at')->get()->map(fn (Consent $c) => [
                'purpose' => $c->purpose,
                'granted' => $c->granted,
                'noticeVersion' => $c->notice_version,
                'at' => $c->created_at->toJSON(),
            ])->all(),
            'journeyProgress' => $user->journeyEvents()->orderBy('created_at')->get()->map(fn (JourneyEvent $e) => [
                'journey' => $e->journey,
                'step' => $e->step,
                'detail' => $e->detail,
                'at' => $e->created_at->toJSON(),
            ])->all(),
            'activity' => AuditLog::where('actor_id', $user->id)->latest('created_at')->limit(500)->get()->map(fn (AuditLog $l) => [
                'action' => $l->action,
                'at' => $l->created_at->toJSON(),
                'ipAddress' => $l->ip_address,
            ])->all(),
        ];
    }

    /**
     * Permanently erases the account and everything linked to it (cards,
     * loans, applications, scores, snapshots, consents, sessions, digital
     * footprints, statement files). Nothing is kept behind a "deleted" flag.
     * The audit trail keeps a record that the deletion happened, without the email.
     */
    public function erase(User $user): void
    {
        foreach ($user->statements()->get() as $statement) {
            $this->vault->delete($statement);
        }

        $this->audit->record('account.deleted', $user, ['email_hash' => sha1($user->email)], actor: $user);

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            // No foreign key on footprints, so they would not cascade
            UserFootprint::where('user_id', $user->id)->delete();
            // Journey steps they took before signing in share their browser id; remove those too
            $visitorIds = $user->journeyEvents()->whereNotNull('visitor_id')->distinct()->pluck('visitor_id');
            JourneyEvent::whereIn('visitor_id', $visitorIds)->delete();
            // Cards, loans, applications, scores, snapshots, consents and
            // sessions go with the user row (cascading foreign keys)
            $user->delete();
        });
    }
}
