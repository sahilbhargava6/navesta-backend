<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LoanType: string implements HasLabel
{
    case Personal = 'personal';
    case Home = 'home';
    case Auto = 'auto';
    case Education = 'education';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Home => 'Home',
            self::Auto => 'Auto',
            self::Education => 'Education',
            self::Other => 'Other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
