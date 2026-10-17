<?php

namespace App\Contracts;

use App\Models\User;

/**
 * A notification deliverable over WhatsApp / SMS (MessagingChannel).
 */
interface SendsMessages
{
    /**
     * @return array{channel: 'whatsapp'|'sms', message: string}
     */
    public function toMessaging(User $notifiable): array;
}
