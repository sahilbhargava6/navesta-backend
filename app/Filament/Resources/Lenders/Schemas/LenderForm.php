<?php

namespace App\Filament\Resources\Lenders\Schemas;

use App\Enums\LenderType;
use App\Support\Money;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Rates are entered in % and amounts in ₹; they are stored as basis points
 * and paise.
 */
class LenderForm
{
    public static function configure(Schema $schema): Schema
    {
        $percent = fn (string $column, string $label) => TextInput::make($column)
            ->label($label)
            ->required()
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01)
            ->suffix('%')
            ->formatStateUsing(fn (?int $state) => $state === null ? null : Money::bpsToPercent($state))
            ->dehydrateStateUsing(fn ($state) => Money::percentToBps($state));

        return $schema
            ->components([
                Section::make('Lender')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(100)->unique(ignoreRecord: true),
                        Select::make('type')->options(LenderType::class)->required(),
                        TextInput::make('initials')->required()->length(2),
                        ColorPicker::make('brand_color')->regex('/^#[0-9A-Fa-f]{6}$/'),
                        Toggle::make('is_active')->label('Active (shown to customers)')->default(true),
                    ]),
                Section::make('Terms')
                    ->columns(2)
                    ->schema([
                        $percent('min_rate_bps', 'Minimum rate'),
                        $percent('max_rate_bps', 'Maximum rate')->gte('min_rate_bps'),
                        $percent('processing_fee_bps', 'Processing fee'),
                        TextInput::make('max_loan_amount_paise')
                            ->label('Maximum amount')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->prefix('₹')
                            ->formatStateUsing(fn (?int $state) => $state === null ? null : Money::paiseToRupees($state))
                            ->dehydrateStateUsing(fn ($state) => Money::rupeesToPaise($state)),
                        TextInput::make('min_credit_score')
                            ->label('Minimum credit score')
                            ->required()
                            ->integer()
                            ->minValue(300)
                            ->maxValue(900)
                            ->default(650),
                        TextInput::make('disbursal_days_min')->label('Disbursal (min days)')->required()->integer()->minValue(0),
                        TextInput::make('disbursal_days_max')->label('Disbursal (max days)')->required()->integer()->minValue(0)->gte('disbursal_days_min'),
                    ]),
            ]);
    }
}
