<?php

namespace App\Filament\Resources\BtApplications\Schemas;

use App\Filament\Support\Display;
use App\Models\BtApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BtApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                        TextEntry::make('updated_at')->label('Last change')->dateTime(),
                        TextEntry::make('user.name')->label('Customer'),
                        TextEntry::make('user.email')->label('Email')->copyable(),
                        TextEntry::make('crif_score')->label('Credit score at submission')->placeholder('Not on file'),
                    ]),
                Section::make('Transfer')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('source')
                            ->label('Moving from')
                            ->state(fn (BtApplication $record) => $record->sourceLoan
                                ? "{$record->sourceLoan->lender_name} {$record->sourceLoan->loan_type->getLabel()} loan"
                                : ($record->sourceCreditCard ? "{$record->sourceCreditCard->bank_name} {$record->sourceCreditCard->card_name}" : '—')),
                        TextEntry::make('lender.name')->label('Moving to'),
                        TextEntry::make('outstanding_paise')->label('Amount')->formatStateUsing(fn (int $state) => Display::rupees($state)),
                        TextEntry::make('current_rate_bps')->label('Current rate')->formatStateUsing(fn (int $state) => Display::percent($state)),
                        TextEntry::make('new_rate_bps')->label('Offered rate')->formatStateUsing(fn (?int $state) => Display::percent($state)),
                        TextEntry::make('requested_tenure_months')->label('Tenure')->suffix(' months'),
                    ]),
            ]);
    }
}
