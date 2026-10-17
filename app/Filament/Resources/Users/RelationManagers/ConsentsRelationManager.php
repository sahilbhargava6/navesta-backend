<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Consent history (read-only): every grant and withdrawal, newest first.
 */
class ConsentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consents';

    protected static ?string $title = 'Consent history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('purpose')->badge(),
                IconColumn::make('granted')->boolean(),
                TextColumn::make('notice_version')->label('Notice version'),
                TextColumn::make('ip_address')->label('IP')->placeholder('—'),
                TextColumn::make('created_at')->label('When')->dateTime(),
            ]);
    }
}
