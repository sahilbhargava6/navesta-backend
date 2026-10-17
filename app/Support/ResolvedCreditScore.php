<?php

namespace App\Support;

use App\Enums\CreditScoreSource;

final readonly class ResolvedCreditScore
{
    public function __construct(
        public ?int $score,
        public ?CreditScoreSource $source,
    ) {}

    public function isKnown(): bool
    {
        return $this->score !== null;
    }

    public function isFromBureau(): bool
    {
        return $this->source !== null && $this->source !== CreditScoreSource::UserInput;
    }

    /**
     * "cibil_api", "crif_api", "user_input" or "none".
     */
    public function sourceLabel(): string
    {
        return $this->source->value ?? 'none';
    }
}
