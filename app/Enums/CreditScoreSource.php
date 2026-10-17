<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CreditScoreSource: string implements HasLabel
{
    case UserInput = 'user_input';
    case CrifApi = 'crif_api';
    case CibilApi = 'cibil_api';

    public function getLabel(): string
    {
        return match ($this) {
            self::UserInput => 'Self-reported',
            self::CrifApi => 'CRIF bureau',
            self::CibilApi => 'CIBIL bureau',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
