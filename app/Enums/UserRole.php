<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Customer = 'customer';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Admin => 'Admin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'gray',
            self::Admin => 'warning',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
