<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LenderType: string implements HasLabel
{
    case Bank = 'bank';
    case Nbfc = 'nbfc';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::Nbfc => 'NBFC',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
