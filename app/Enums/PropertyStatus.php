<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropertyStatus: string implements HasLabel
{
    case Owned = 'owned';
    case Rented = 'rented';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owned => 'Owned',
            self::Rented => 'Rented',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
