<?php

namespace App\Filament\Resources\Inquiries;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Follow-up buttons for a lead: Mark contacted / Resolve / Reopen. Each move
 * is written to the audit log with the admin who made it.
 */
final class StatusActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::move('mark_contacted', InquiryStatus::Contacted, 'Mark contacted', 'info', Heroicon::OutlinedPhone, [InquiryStatus::Pending]),
            self::move('resolve', InquiryStatus::Resolved, 'Resolve', 'success', Heroicon::OutlinedCheckCircle, [InquiryStatus::Pending, InquiryStatus::Contacted]),
            self::move('reopen', InquiryStatus::Pending, 'Reopen', 'gray', Heroicon::OutlinedArrowUturnLeft, [InquiryStatus::Contacted, InquiryStatus::Resolved]),
        ];
    }

    /**
     * @param  list<InquiryStatus>  $from
     */
    private static function move(string $name, InquiryStatus $to, string $label, string $color, Heroicon $icon, array $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->visible(fn (Inquiry $record) => in_array($record->status, $from, true))
            ->action(function (Inquiry $record) use ($to) {
                $before = $record->status;
                $record->update(['status' => $to]);

                app(AuditLogger::class)->record('inquiry.status_changed', $record, ['from' => $before->value, 'to' => $to->value]);

                Notification::make()->title("Marked {$to->getLabel()}")->success()->send();
            });
    }
}
