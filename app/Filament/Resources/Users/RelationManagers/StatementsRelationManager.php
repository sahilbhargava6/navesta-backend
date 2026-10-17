<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * File details and scan status only. Admins cannot open customer
 * statements from the panel.
 */
class StatementsRelationManager extends RelationManager
{
    protected static string $relationship = 'statements';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_name')->label('File'),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn (int $state) => Number::fileSize($state)),
                TextColumn::make('status')->badge(),
                TextColumn::make('scan_signature')->label('Threat found')->placeholder('—'),
                TextColumn::make('created_at')->label('Uploaded')->dateTime(),
            ]);
    }
}
