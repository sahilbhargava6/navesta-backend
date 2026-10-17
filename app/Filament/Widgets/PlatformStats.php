<?php

namespace App\Filament\Widgets;

use App\Enums\BtStatus;
use App\Enums\UserRole;
use App\Filament\Support\Display;
use App\Models\BtApplication;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $monthStart = now(config('navesta.timezone'))->startOfMonth();

        $customers = User::where('role', UserRole::Customer);
        $debtTracked = (int) CreditCard::active()->sum('outstanding_paise') + (int) Loan::active()->sum('outstanding_paise');
        $pending = BtApplication::where('status', BtStatus::Submitted);
        $approvedThisMonth = BtApplication::whereIn('status', [BtStatus::Approved, BtStatus::Disbursed])
            ->where('updated_at', '>=', $monthStart);

        return [
            Stat::make('Customers', (clone $customers)->count())
                ->description((clone $customers)->where('created_at', '>=', $monthStart)->count().' joined this month'),
            Stat::make('Debt tracked', Display::rupees($debtTracked))
                ->description('Active cards and loans'),
            Stat::make('Applications to review', (clone $pending)->count())
                ->description(Display::rupees((int) (clone $pending)->sum('outstanding_paise')).' requested')
                ->color('warning'),
            Stat::make('Approved this month', (clone $approvedThisMonth)->count())
                ->description(Display::rupees((int) (clone $approvedThisMonth)->sum('outstanding_paise')).' transferred or approved')
                ->color('success'),
        ];
    }
}
