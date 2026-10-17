<?php

namespace App\Support;

final readonly class ScanResult
{
    /**
     * @param  bool|null  $clean  null = not scanned (scanning disabled)
     */
    public function __construct(
        public ?bool $clean,
        public ?string $signature = null,
    ) {}

    public static function clean(): self
    {
        return new self(true);
    }

    public static function infected(string $signature): self
    {
        return new self(false, $signature);
    }

    public static function skipped(): self
    {
        return new self(null);
    }
}
