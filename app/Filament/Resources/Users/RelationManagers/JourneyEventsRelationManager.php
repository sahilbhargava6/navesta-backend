<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\JourneyEvent;
use App\Support\JourneyCatalog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Every journey step the customer reached on the website, newest first (read-only).
 */
class JourneyEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'journeyEvents';

    protected static ?string $title = 'Journey activity';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->description(fn (JourneyEvent $record) => $record->created_at->diffForHumans()),
                TextColumn::make('journey')->badge()->formatStateUsing(fn (string $state) => JourneyCatalog::journeyLabel($state)),
                TextColumn::make('step')->formatStateUsing(fn (string $state, JourneyEvent $record) => JourneyCatalog::stepLabel($record->journey, $state)),
                TextColumn::make('detail')->placeholder('—')->formatStateUsing(fn (?string $state) => $state ? str_replace(['_', '-'], ' ', $state) : null),
            ])
            ->filters([
                SelectFilter::make('journey')->options(array_map(fn (array $j) => $j['label'], JourneyCatalog::JOURNEYS)),
            ]);
    }
}
