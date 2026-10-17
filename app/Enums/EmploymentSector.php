<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentSector: string implements HasLabel
{
    case Govt = 'govt';
    case Pvt = 'pvt';
    case SelfEmployed = 'self';

    public function getLabel(): string
    {
        return match ($this) {
            self::Govt => 'Government',
            self::Pvt => 'Private sector',
            self::SelfEmployed => 'Self-employed',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
