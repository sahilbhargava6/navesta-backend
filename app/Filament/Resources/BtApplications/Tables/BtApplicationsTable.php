<?php

namespace App\Filament\Resources\BtApplications\Tables;

use App\Enums\BtStatus;
use App\Filament\Resources\BtApplications\ReviewActions;
use App\Filament\Support\Display;
use App\Models\BtApplication;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BtApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'lender']))
            ->columns([
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('lender.name')->label('Lender')->searchable(),
                TextColumn::make('outstanding_paise')->label('Amount')->formatStateUsing(fn (int $state) => Display::rupees($state))->sortable(),
                TextColumn::make('current_rate_bps')->label('From')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('new_rate_bps')->label('To')->formatStateUsing(fn (?int $state) => Display::percent($state)),
                TextColumn::make('crif_score')->label('Score')->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BtStatus::class)->default(BtStatus::Submitted->value),
                SelectFilter::make('lender')->relationship('lender', 'name'),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['ID', 'Customer', 'Lender', 'Outstanding (Paise)', 'Current APR (bps)', 'New APR (bps)', 'Tenure (Months)', 'Status', 'Submitted At']);
                            BtApplication::withTrashed()->with(['user', 'lender'])->chunk(250, function ($apps) use ($handle) {
                                foreach ($apps as $app) {
                                    fputcsv($handle, [
                                        $app->id,
                                        $app->user?->name ?? 'Unknown',
                                        $app->lender?->name ?? 'Unknown',
                                        $app->outstanding_paise,
                                        $app->current_rate_bps,
                                        $app->new_rate_bps,
                                        $app->requested_tenure_months,
                                        $app->status?->value ?? (string) $app->status,
                                        $app->created_at?->toIso8601String(),
                                    ]);
                                }
                            });
                            fclose($handle);
                        }, 'balance-transfer-apps-' . date('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ReviewActions::make()),
            ]);
    }
}
