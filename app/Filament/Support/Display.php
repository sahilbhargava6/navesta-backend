<?php

namespace App\Filament\Support;

use App\Support\Money;

/**
 * Formatting for stored units (paise, basis points) in the admin panel.
 */
final class Display
{
    public static function rupees(?int $paise): ?string
    {
        return $paise === null ? null : '₹'.Money::formatInr(Money::paiseToRupees($paise));
    }

    public static function percent(?int $bps): ?string
    {
        return $bps === null ? null : Money::bpsToPercent($bps).'%';
    }
}
