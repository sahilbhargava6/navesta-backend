<?php

namespace App\Notifications\Channels;

use App\Contracts\MessageGateway;
use App\Contracts\SendsMessages;
use App\Models\User;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * Notification channel for WhatsApp / SMS.
 */
class MessagingChannel
{
    public function __construct(private MessageGateway $gateway) {}

    public function send(User $notifiable, Notification $notification): ?string
    {
        if (! $notification instanceof SendsMessages) {
            throw new InvalidArgumentException($notification::class.' must implement '.SendsMessages::class);
        }

        $to = $notifiable->routeNotificationFor('messaging', $notification);
        if (! $to) {
            return null;
        }

        ['channel' => $channel, 'message' => $message] = $notification->toMessaging($notifiable);

        return $this->gateway->send($channel, $to, $message);
    }
}
