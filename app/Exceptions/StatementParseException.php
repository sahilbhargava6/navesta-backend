<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A statement could not be read. `reason` is a stable code the website acts on
 * (password_required, wrong_password, bad_file, busy, expired, unavailable);
 * the message is safe to show the customer.
 */
class StatementParseException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }
}
