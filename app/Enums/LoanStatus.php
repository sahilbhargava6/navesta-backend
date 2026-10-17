<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Closed = 'closed';
    case Defaulted = 'defaulted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Defaulted => 'Defaulted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Closed => 'gray',
            self::Defaulted => 'danger',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
