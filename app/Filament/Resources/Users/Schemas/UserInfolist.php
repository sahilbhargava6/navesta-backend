<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\ConsentService;
use App\Support\JourneyCatalog;
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
                Section::make('Journey progress')
                    ->description('Where this customer got to on the website. Full history under "Journey activity" below.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('journey_progress')
                            ->label('Furthest step per journey')
                            ->state(fn (User $record) => JourneyCatalog::progressLines($record->journeyEvents()->get()) ?: null)
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('No journey activity yet')
                            ->columnSpan(2),
                        TextEntry::make('follow_up')
                            ->label('Follow-up calls')
                            ->state(fn (User $record) => self::mayContact($record) ? 'Allowed' : 'Not allowed')
                            ->badge()
                            ->color(fn (string $state) => $state === 'Allowed' ? 'success' : 'warning')
                            ->helperText(fn (User $record) => self::mayContact($record)
                                ? 'Agreed to hear from Navesta (marketing consent).'
                                : 'No marketing consent: don’t call about their plan unless they contact you.'),
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

    /** Advisors may call about the customer's plan only with their marketing consent (DPDP Act). */
    private static function mayContact(User $user): bool
    {
        return (bool) (app(ConsentService::class)->current($user)['marketing']['granted'] ?? false);
    }
}
