<?php

namespace App\Support;

/**
 * Outcome of a pincode check: verified (with the place), not found (the
 * pincode does not exist), or unavailable (no provider could answer).
 */
final class PincodeLookup
{
    public const VERIFIED = 'verified';

    public const NOT_FOUND = 'not_found';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly string $pincode,
        public readonly string $city = '',
        public readonly string $district = '',
        public readonly string $state = '',
        public readonly ?string $locality = null,
        public readonly ?string $source = null,
    ) {}

    public static function verified(string $pincode, string $city, string $district, string $state, ?string $locality, string $source): self
    {
        return new self(self::VERIFIED, $pincode, $city, $district, $state, $locality, $source);
    }

    public static function notFound(string $pincode, ?string $source = null): self
    {
        return new self(self::NOT_FOUND, $pincode, source: $source);
    }

    public static function unavailable(string $pincode): self
    {
        return new self(self::UNAVAILABLE, $pincode);
    }

    /** Plain array for the cache (no object unserialization needed). */
    public function toCache(): array
    {
        return get_object_vars($this);
    }

    public static function fromCache(mixed $data): ?self
    {
        if (! is_array($data) || ! in_array($data['status'] ?? null, [self::VERIFIED, self::NOT_FOUND], true)) {
            return null;
        }

        return new self($data['status'], (string) $data['pincode'], (string) $data['city'], (string) $data['district'], (string) $data['state'], $data['locality'] ?? null, $data['source'] ?? null);
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pincode' => $this->pincode,
            'city' => $this->city,
            'district' => $this->district,
            'state' => $this->state,
            'locality' => $this->locality,
            'verified' => true,
            'source' => $this->source,
        ];
    }
}
