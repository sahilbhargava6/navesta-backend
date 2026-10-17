<?php

namespace App\Services\Messaging;

use App\Contracts\MessageGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development gateway: writes messages to the log instead of sending them.
 */
class LogGateway implements MessageGateway
{
    public function send(string $channel, string $to, string $message): string
    {
        $id = 'log-'.Str::uuid();

        Log::info('Message not sent (MESSAGING_DRIVER=log)', [
            'id' => $id,
            'channel' => $channel,
            'to' => $to,
            'message' => $message,
        ]);

        return $id;
    }
}
