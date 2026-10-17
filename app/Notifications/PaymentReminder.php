<?php

namespace App\Notifications;

use App\Contracts\SendsMessages;
use App\Models\User;
use App\Notifications\Channels\MessagingChannel;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * "Your EMI / minimum due is coming up" message, sent by WhatsApp or SMS
 * from the queue.
 */
class PaymentReminder extends Notification implements SendsMessages, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * @param  'whatsapp'|'sms'  $channel
     */
    public function __construct(
        public string $channel,
        public string $accountName,
        public float|int $amountRupees,
        public string $dueDate,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return [MessagingChannel::class];
    }

    /**
     * @return array{channel: string, message: string}
     */
    public function toMessaging(User $notifiable): array
    {
        return ['channel' => $this->channel, 'message' => $this->content($notifiable)];
    }

    public function content(User $user): string
    {
        $firstName = Str::before($user->name, ' ');
        $amount = Money::formatInr($this->amountRupees);

        return $this->channel === 'whatsapp'
            ? "📲 *Navesta Alert*: Hi {$firstName}! Your monthly EMI / minimum due for *{$this->accountName}* (₹{$amount}) is due on *{$this->dueDate}*. Pay on time to avoid late fees and high interest charges. Balance transfer options are in your Navesta dashboard."
            : "Navesta Alert: EMI payment of Rs.{$amount} for {$this->accountName} is due on {$this->dueDate}. Avoid late fee by paying on time.";
    }
}
