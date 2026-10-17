<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Jobs\SendRemindersBatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendDueReminders extends Command
{
    protected $signature = 'navesta:send-due-reminders {--dry-run : List the reminders without sending them}';

    protected $description = 'Queue WhatsApp reminders for card and loan payments due in N days (navesta.reminders.days_before_due)';

    private const BATCH_SIZE = 500;

    public function handle(): int
    {
        $due = now(config('navesta.timezone'))->startOfDay()->addDays((int) config('navesta.reminders.days_before_due'));

        $customers = User::where('role', UserRole::Customer)
            ->where('reminders_enabled', true)
            ->whereNotNull('phone')
            ->where(fn (Builder $q) => $q
                ->whereHas('creditCards', fn (Builder $c) => $c->active()->where('due_day', $due->day)->where('outstanding_paise', '>', 0))
                ->orWhereHas('loans', fn (Builder $l) => $l->active()->whereDate('next_due_date', $due->toDateString())));

        if ($this->option('dry-run')) {
            $count = 0;
            $customers->each(function (User $user) use ($due, &$count) {
                foreach (SendRemindersBatch::remindersFor($user, $due) as $reminder) {
                    $this->line("{$user->email}: {$reminder->accountName} ₹{$reminder->amountRupees}");
                    $count++;
                }
            });
            $this->info("Would queue {$count} reminders for payments due {$due->format('jS F')}.");

            return self::SUCCESS;
        }

        $batches = 0;
        $customerCount = 0;
        $customers->select('id')->chunkById(self::BATCH_SIZE, function ($users) use ($due, &$batches, &$customerCount) {
            SendRemindersBatch::dispatch($users->pluck('id')->all(), $due->toDateString());
            $batches++;
            $customerCount += $users->count();
        });

        $this->info("Queued reminders for {$customerCount} customers ({$batches} batches) for payments due {$due->format('jS F')}.");

        return self::SUCCESS;
    }
}
