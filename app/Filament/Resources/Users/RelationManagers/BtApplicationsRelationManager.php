<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\BtApplications\BtApplicationResource;
use App\Filament\Support\Display;
use App\Models\BtApplication;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only list; applications are reviewed on the Balance transfers page.
 */
class BtApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'btApplications';

    protected static ?string $title = 'Balance transfer applications';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('lender.name')->label('Lender'),
                TextColumn::make('outstanding_paise')->label('Amount')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                TextColumn::make('current_rate_bps')->label('Current rate')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('new_rate_bps')->label('New rate')->formatStateUsing(fn (?int $state) => Display::percent($state)),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Submitted')->dateTime(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn (BtApplication $record) => BtApplicationResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
