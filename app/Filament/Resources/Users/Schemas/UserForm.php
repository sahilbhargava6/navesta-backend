<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\EmploymentSector;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->minLength(2)->maxLength(120),
                        TextInput::make('email')->email()->required()->maxLength(200)->unique(ignoreRecord: true),
                        TextInput::make('phone')->tel()->maxLength(15),
                        Select::make('property_status')->options(PropertyStatus::class),
                        Select::make('employment_sector')->options(EmploymentSector::class),
                    ]),
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->options(UserRole::class)
                            ->required()
                            // An admin cannot remove their own access by accident
                            ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                            ->helperText('Admins can sign in to this panel.'),
                        Toggle::make('is_verified')->label('Verified'),
                        Toggle::make('reminders_enabled')->label('Payment reminders'),
                    ]),
            ]);
    }
}
