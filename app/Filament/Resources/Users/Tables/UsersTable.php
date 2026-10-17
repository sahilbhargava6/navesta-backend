<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['creditCards', 'loans', 'btApplications']))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('role')->badge(),
                IconColumn::make('is_verified')->label('Verified')->boolean(),
                TextColumn::make('credit_cards_count')->label('Cards')->sortable(),
                TextColumn::make('loans_count')->label('Loans')->sortable(),
                TextColumn::make('bt_applications_count')->label('BT apps')->sortable(),
                TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                TernaryFilter::make('is_verified')->label('Verified'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
