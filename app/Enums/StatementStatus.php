<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatementStatus: string implements HasColor, HasLabel
{
    case PendingScan = 'pending_scan';
    case Clean = 'clean';
    case Unscanned = 'unscanned';
    case Infected = 'infected';
    case ScanFailed = 'scan_failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingScan => 'Pending scan',
            self::Clean => 'Clean',
            self::Unscanned => 'Not scanned',
            self::Infected => 'Infected',
            self::ScanFailed => 'Scan failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingScan => 'warning',
            self::Clean => 'success',
            self::Unscanned => 'gray',
            self::Infected => 'danger',
            self::ScanFailed => 'danger',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
