<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\Display;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: customers manage their own cards in the app.
 */
class CreditCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'creditCards';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('bank_name')->label('Bank'),
                TextColumn::make('card_name')->label('Card'),
                TextColumn::make('last_four')->label('Last 4')->placeholder('—'),
                TextColumn::make('credit_limit_paise')->label('Limit')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                TextColumn::make('outstanding_paise')->label('Outstanding')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                TextColumn::make('apr_bps')->label('APR')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('due_day')->label('Due day')->placeholder('—'),
                TextColumn::make('status')->badge(),
            ]);
    }
}
