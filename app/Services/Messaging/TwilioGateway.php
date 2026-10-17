<?php

namespace App\Services\Messaging;

use App\Contracts\MessageGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends SMS and WhatsApp messages through Twilio's Messages API.
 *
 * SMS to Indian numbers requires a DLT-registered sender and template;
 * WhatsApp requires an approved WhatsApp Business sender and, outside a
 * 24-hour customer session, an approved message template.
 */
class TwilioGateway implements MessageGateway
{
    public function __construct(
        private string $accountSid,
        private string $authToken,
        private ?string $smsFrom,
        private ?string $whatsappFrom,
    ) {}

    public function send(string $channel, string $to, string $message): string
    {
        $from = $channel === 'whatsapp' ? $this->whatsappFrom : $this->smsFrom;
        if (! $from) {
            throw new RuntimeException("No Twilio sender configured for {$channel}");
        }

        $prefix = $channel === 'whatsapp' ? 'whatsapp:' : '';

        $response = Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'To' => $prefix.$to,
                'From' => $prefix.$from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Twilio rejected the message ({$response->status()}): ".$response->json('message', 'unknown error'));
        }

        return (string) $response->json('sid');
    }
}
