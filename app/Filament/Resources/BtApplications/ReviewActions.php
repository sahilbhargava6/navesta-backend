<?php

namespace App\Filament\Resources\BtApplications;

use App\Enums\BtStatus;
use App\Models\BtApplication;
use App\Services\BalanceTransferService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Approve / reject / mark disbursed. Each action only shows when the
 * workflow allows that move (BtStatus::canTransitionTo).
 */
final class ReviewActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::transition('approve', BtStatus::Approved, 'Approve', 'success', Heroicon::OutlinedCheckCircle),
            self::transition('reject', BtStatus::Rejected, 'Reject', 'danger', Heroicon::OutlinedXCircle),
            self::transition('disburse', BtStatus::Disbursed, 'Mark disbursed', 'primary', Heroicon::OutlinedBanknotes),
        ];
    }

    private static function transition(string $name, BtStatus $to, string $label, string $color, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->visible(fn (BtApplication $record) => $record->status->canTransitionTo($to))
            ->requiresConfirmation()
            ->modalDescription(fn (BtApplication $record) => "{$label} the application from {$record->user->name} to {$record->lender->name}?")
            ->action(function (BtApplication $record) use ($to) {
                app(BalanceTransferService::class)->transition($record, $to);

                Notification::make()->title("Application marked {$to->getLabel()}")->success()->send();
            });
    }
}
