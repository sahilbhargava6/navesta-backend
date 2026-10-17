<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('property_status')->placeholder('—'),
                        TextEntry::make('employment_sector')->placeholder('—'),
                        TextEntry::make('latestCreditScore.score')->label('Latest credit score')->placeholder('None on file'),
                    ]),
                Section::make('Account')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('role')->badge(),
                        IconEntry::make('is_verified')->label('Verified')->boolean(),
                        IconEntry::make('reminders_enabled')->label('Payment reminders')->boolean(),
                        TextEntry::make('created_at')->label('Joined')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
