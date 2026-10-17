<?php

namespace App\Filament\Resources\Lenders\Tables;

use App\Enums\LenderType;
use App\Filament\Support\Display;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LendersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('min_rate_bps')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('min_rate_bps')->label('Rate from')->formatStateUsing(fn (int $state) => Display::percent($state))->sortable(),
                TextColumn::make('max_rate_bps')->label('Rate to')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('max_loan_amount_paise')->label('Max amount')->formatStateUsing(fn (int $state) => Display::rupees($state))->sortable(),
                TextColumn::make('processing_fee_bps')->label('Fee')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('min_credit_score')->label('Min score')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(LenderType::class),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
