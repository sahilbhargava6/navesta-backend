<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\Display;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: customers manage their own loans in the app.
 */
class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('lender_name')->label('Lender'),
                TextColumn::make('loan_type')->label('Type')->badge(),
                TextColumn::make('principal_paise')->label('Principal')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                TextColumn::make('outstanding_paise')->label('Outstanding')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                TextColumn::make('interest_rate_bps')->label('Rate')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('emi_paise')->label('EMI')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                TextColumn::make('tenure_remaining_months')->label('Months left'),
                TextColumn::make('next_due_date')->label('Next due')->date()->placeholder('—'),
                TextColumn::make('status')->badge(),
            ]);
    }
}
