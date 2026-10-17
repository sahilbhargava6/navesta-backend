<?php

namespace App\Contracts;

interface MessageGateway
{
    /**
     * Deliver a text message.
     *
     * @param  'whatsapp'|'sms'  $channel
     * @param  string  $to  E.164 phone number, e.g. +919876543210
     * @return string the provider's message id
     */
    public function send(string $channel, string $to, string $message): string;
}
