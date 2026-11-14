<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a lead or contact-form message is in the follow-up: new, someone has
 * called / written back, or closed.
 */
enum InquiryStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'New',
            self::Contacted => 'Contacted',
            self::Resolved => 'Resolved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Contacted => 'info',
            self::Resolved => 'success',
        };
    }
}
