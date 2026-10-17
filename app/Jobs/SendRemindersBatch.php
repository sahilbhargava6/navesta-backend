<?php

namespace App\Jobs;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\PaymentReminder;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Reminders for one batch of customers with a payment due on $dueDate.
 * Each reminder is itself queued on the "notifications" queue.
 */
class SendRemindersBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $userIds
     */
    public function __construct(public array $userIds, public string $dueDate) {}

    public function handle(): void
    {
        $due = Carbon::parse($this->dueDate);

        User::whereIn('id', $this->userIds)->each(function (User $user) use ($due) {
            foreach (self::remindersFor($user, $due) as $reminder) {
                $user->notify($reminder);
            }
        });
    }

    /**
     * @return list<PaymentReminder>
     */
    public static function remindersFor(User $user, Carbon $due): array
    {
        $label = $due->format('jS F');

        $cards = $user->creditCards()->active()
            ->where('due_day', $due->day)
            ->where('outstanding_paise', '>', 0)
            ->get()
            ->map(fn (CreditCard $c) => new PaymentReminder(
                'whatsapp',
                "{$c->bank_name} {$c->card_name}",
                Money::paiseToRupees($c->min_payment_paise ?: $c->outstanding_paise),
                $label,
            ));

        $loans = $user->loans()->active()
            ->whereDate('next_due_date', $due->toDateString())
            ->get()
            ->map(fn (Loan $l) => new PaymentReminder(
                'whatsapp',
                "{$l->lender_name} {$l->loan_type->getLabel()} loan",
                Money::paiseToRupees($l->emi_paise),
                $label,
            ));

        return [...$cards->all(), ...$loans->all()];
    }
}
