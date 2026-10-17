<?php

namespace App\Support;

/**
 * Conversions between API values (rupees, percentages) and stored values
 * (integer paise, integer basis points).
 */
final class Money
{
    public static function rupeesToPaise(float|int|string $rupees): int
    {
        return (int) round((float) $rupees * 100);
    }

    public static function paiseToRupees(int $paise): int|float
    {
        return self::num($paise / 100);
    }

    public static function percentToBps(float|int|string $percent): int
    {
        return (int) round((float) $percent * 100);
    }

    public static function bpsToPercent(int $bps): int|float
    {
        return self::num($bps / 100);
    }

    /**
     * JavaScript-style Math.round (halves round towards +infinity), so figures
     * match what the Node API returned.
     */
    public static function round(float|int $value): int
    {
        return (int) floor($value + 0.5);
    }

    /**
     * Whole floats become ints so JSON shows 17210 rather than 17210.0.
     */
    public static function num(float|int $value): int|float
    {
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Indian digit grouping, e.g. 125000 -> "1,25,000".
     */
    public static function formatInr(float|int $rupees): string
    {
        return (new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL))->format($rupees);
    }
}
