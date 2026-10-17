<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\CreditScoreSource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Score history. Admins can record a score from a bureau report until the
 * bureau API integration writes them automatically; scores are never edited.
 */
class CreditScoresRelationManager extends RelationManager
{
    protected static string $relationship = 'creditScores';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('score')->required()->integer()->minValue(300)->maxValue(900),
                Select::make('source')->options(CreditScoreSource::class)->required()->default(CreditScoreSource::CibilApi),
                DateTimePicker::make('fetched_at')->label('Report date')->required()->default(now())->maxDate(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('fetched_at', 'desc')
            ->columns([
                TextColumn::make('score'),
                TextColumn::make('source')->badge(),
                TextColumn::make('fetched_at')->label('Report date')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()->label('Record score'),
            ]);
    }
}
